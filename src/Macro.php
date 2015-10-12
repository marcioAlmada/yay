<?php declare(strict_types=1);

namespace Yay;

class Macro extends Directive {

    const
        PRETTY_PRINT =
                JSON_PRETTY_PRINT
            |   JSON_BIGINT_AS_STRING
            |   JSON_UNESCAPED_UNICODE
            |   JSON_UNESCAPED_SLASHES
    ;

    const
        E_EMPTY_PATTERN = "Empty macro pattern on line %d.",
        E_EMPTY_EXPANSION = "Empty macro expansion on line %d.",
        E_BAD_CAPTURE = "Bad macro capture identifier '%s' on line %d.",
        E_BAD_EXPANSION = "Bad macro expansion identifier '%s' on line %d.",
        E_PARSER = "Bad macro parser identifier '%s' on line %d.",
        E_EXPANDER = "Bad macro expander '%s' on line %d.",
        E_LOOKUP = "Redefinition of macro capture identifier '%s' on line %d.",
        E_EXPANSION = "Undefined macro expansion '%s' on line %d with context: %s"
    ;

    protected
        $pattern,
        $expansion,
        $lookup = [],
        $parsers = [],
        $specificity = 0,
        $dominant = false,
        $constant = true
    ;

    function __construct(int $line, array $pattern, array $expansion) {
        $this->pattern = $this->compilePattern($line, $pattern);
        $this->expansion = $this->compileExpansion($line, $expansion);
    }

    function specificity() : int {
        return $this->specificity;
    }

    function apply(TokenStream $ts) {
        $from = $ts->index();
        $crossover = $this->pattern->parse($ts);
        if ($crossover instanceof ast && ! $crossover->isEmpty()) {
            $ts->unskip(TokenStream::SKIPPABLE);
            $to = $ts->index();
            $expansion = $this->mutate($this->expansion, $crossover);
            $ts->inject($expansion, $from, $to);
            $ts->step(-1);
        }
    }

    private function compilePattern(int $line, array $pattern) : Parser {
        if(! $pattern) $this->fail(self::E_EMPTY_PATTERN, $line);

        passthru
        (
            either
            (
                rtoken('/^(T_\w+)·(\w+)$/')
                    ->onCommit(function(Ast $result) {
                        $token = $result->token();
                        $id = $this->lookupCapture($token);
                        $type = $this->lookupTokenType($token);
                        $this->parsers[] = token($type)->as($id);
                    })
                ,
                (
                    $parser = chain
                    (
                        rtoken('/^·\w+$/')->as('parser_type')
                        ,
                        token('(')
                        ,
                        optional
                        (
                            ls
                            (
                                either
                                (
                                    future
                                    (
                                        $parser // recursion !!!
                                    )
                                    ,
                                    token(T_CONSTANT_ENCAPSED_STRING)
                                    ,
                                    rtoken('/^(T_\w+)·(\w+)$/')
                                    ,
                                    rtoken('/^T_\w+$/')
                                    ,
                                    rtoken('/^\w+$/')
                                )
                                ,
                                token(',')
                            )
                        )
                        ->as('args')
                        ,
                        commit
                        (
                            token(')')
                        )
                        ,
                        optional
                        (
                            rtoken('/^·\w+$/')->as('label')
                        )
                    )
                )
                ->onCommit(function(Ast $result) {
                    $this->parsers[] = $this->compileParser($result->array());
                })
                ,
                // handles {···layer}
                $this->layer('{', '}', braces())
                ,
                // handles [···layer]
                $this->layer('[', ']', brackets())
                ,
                // handles (···layer)
                $this->layer('(', ')', parentheses())
                ,
                // handles  non delimited ···layer
                rtoken('/^···(\w+)$/')
                    ->onCommit(function(Ast $result) {
                        $id = $this->lookupCapture($result->token());
                        $this->parsers[] = layer()->as($id);
                    })
                ,
                consume
                (
                    rtoken('/^··$/')
                )
                ->onCommit(function(Ast $result) {
                    $this->dominant = count($this->parsers);
                })
                ,
                rtoken('/·/')
                    ->onCommit(function(Ast $result) {
                        $token = $result->token();
                        $this->fail(self::E_BAD_CAPTURE, $token, $token->line());
                    })
                ,
                any()
                    ->onCommit(function(Ast $result) {
                        $this->parsers[] = token($result->token());
                    })
            )
        )
        ->parse(TokenStream::fromSlice($pattern));

        $this->specificity = count($this->parsers);

        if (count($this->parsers) > 1)
            $pattern = chain(...$this->parsers);
        else
            $pattern = $this->parsers[0];

        return $pattern;
    }

    private function compileExpansion(int $line, array $expansion) : TokenStream {
        if(! $expansion) $this->fail(self::E_EMPTY_EXPANSION, $line);

        $ts = TokenStream::fromSlice($expansion);
        $ts->trim();

        passthru
        (
            either
            (
                chain
                (
                    rtoken('/^·\w+$/')->as('expander')
                    ,
                    token('(')
                    ,
                    ls
                    (
                        rtoken('/^\w+|·\w+|T_\w+·\w+|···\w+$/')
                        ,
                        token(',')
                    )
                    ->as('args')
                    ,
                    commit
                    (
                        token(')')
                    )
                )
                ->onCommit(function($r){
                    $this->constant = false;
                })
                ,
                chain
                (
                    rtoken('/^·\w+|···\w+$/')->as('label')
                    ,
                    operator('···')
                    ,
                    braces()->as('expansion')
                )
                ->onCommit(function(Ast $result){
                    if (! isset($this->lookup[$id = (string) $result->label]))
                        $this->fail(
                            self::E_EXPANSION,
                            $id,
                            $result->label->line(),
                            json_encode (
                                array_keys($this->lookup),
                                self::PRETTY_PRINT
                            )
                        );
                    $this->constant = false;
                })
                ,
                rtoken('/^·\w+|···\w+|T_\w+·\w+$/')
                    ->onCommit(function(Ast $result) {
                        $token = $result->token();
                        if (! isset($this->lookup[$id = (string) $token]))
                            $this->fail(
                                self::E_EXPANSION,
                                $id,
                                $token->line(),
                                json_encode (
                                    array_keys($this->lookup),
                                    self::PRETTY_PRINT
                                )
                            );
                        $this->constant = false;
                    })
                ,
                rtoken('/·/')
                    ->onCommit(function(Ast $result) {
                        $token = $result->token();
                        $this->fail(self::E_BAD_EXPANSION, $token, $token->line());
                    })
                ,
                any()
            )
        )
        ->parse($ts);

        $ts->reset();

        return $ts;
    }

    private function compileParser(array $result) : Parser
    {
        $parser = $this->lookupParser($result['parser_type']);
        $args = $this->compileParserArgs($result['args']);
        $parser = $parser(...$args);

        if ($label = $result['label'])
            $parser->as($this->lookupCapture($label));

        return $parser;
    }

    private function compileParserArgs(array $args) : array {
        $compiled = [];
        foreach ($args as $i => $arg)
            if ($arg instanceof token) {
                $compiled[] = $this->compileParserArg($arg);
            }
            else if(is_array($arg)) {
                if (isset($arg['parser_type']))
                    $compiled[] = $this->compileParser($arg);
                else
                    array_merge($compiled, $this->compileParserArgs($arg));
            }

        return $compiled;
    }

    private function compileParserArg(Token $arg) {
        switch ($arg->type()) {
            case T_CONSTANT_ENCAPSED_STRING:
                $val = trim((string) $arg, '"\'');
                if (1 === mb_strlen($val))
                    $arg = token($val);
                else
                    $arg = $val;
                break;
            case T_STRING:
                if (preg_match('/^(T_\w+)·(\w+)$/', (string) $arg)) {
                    $id = $this->lookupCapture($arg);
                    $type = $this->lookupTokenType($arg);
                    $arg = token($type)->as($id);
                }
                elseif (preg_match('/^(T_\w+)$/', (string) $arg)) {
                    $arg = token($this->lookupTokenType($arg));
                }
                else {
                    $arg = token(T_STRING, (string) $arg);
                }
                break;
            default: // non T_STRING wordy token
                $arg = token($arg->type());
                break;
        }

        return $arg;
    }

    private function mutate(TokenStream $ts, Ast $crossover) : TokenStream {

        if ($this->constant) return $ts;

        $cg = (object) [
            'ts' => clone $ts,
            'crossover' => $crossover,
            // 'frames' => [] // @TODO switch frames instead of merging context
        ];

        passthru
        (
            either
            (
                consume
                (
                    chain
                    (
                        rtoken('/^·\w+$/')->as('expander')
                        ,
                        token('(')
                        ,
                        ls
                        (
                            rtoken('/^\w+|·\w+|T_\w+·\w+|···\w+$/')
                            ,
                            token(',')
                        )
                        ->as('args')
                        ,
                        commit
                        (
                            token(')')
                        )
                    )
                )
                ->onCommit(function(Ast $result) use ($cg) {
                    $expander = $this->lookupExpander($result->expander);
                    $args = [];
                    foreach ($result->args as $arg) {
                        if (preg_match('/^·\w+|T_\w+·\w+|···\w+$/', $key = (string) $arg)) {
                            $arg = $cg->crossover->{$key};
                        }

                        if (is_array($arg))
                            array_push($args, ...$arg);
                        else
                            $args[] = $arg;
                    }
                    $mutation = $expander(TokenStream::fromSlice($args));
                    $cg->ts->inject($mutation);
                })
                ,
                consume
                (
                    chain
                    (
                        rtoken('/^·\w+|···\w+$/')->as('label')
                        ,
                        operator('···')
                        ,
                        braces()->as('expansion')
                    )
                )
                ->onCommit(function(Ast $result)  use($cg) {
                    $crossovers = $cg->crossover->{(string) $result->label};
                    foreach (array_reverse($crossovers) as $context) {
                        $expansion = TokenStream::fromSlice($result->expansion);
                        if(! is_array($context)) $context = [$context];
                        $context = new Ast(
                            null, array_merge($context, $cg->crossover->all()[0]));
                        $mutation = $this->mutate($expansion, $context);
                        $cg->ts->inject($mutation);
                    }
                })
                ,
                consume
                (
                    chain
                    (
                        token(T_NS_SEPARATOR)
                        ,
                        token(T_STRING, '·n')
                    )
                )
                ->onCommit(function(Ast $result) use($cg) {
                    $cg->ts->push(new Token(T_WHITESPACE, PHP_EOL));
                })
                ,
                consume
                (
                    rtoken('/^T_\w+·\w+$/')
                )
                ->onCommit(function(Ast $result) use($cg) {
                    $mutation = $cg->crossover->{(string) $result->token()};
                    $cg->ts->inject(TokenStream::fromSequence($mutation));
                })
                ,
                consume
                (
                    rtoken('/^·\w+|···\w+$/')
                )
                ->onCommit(function(Ast $result) use ($cg) {
                    $c = $cg->crossover->{(string) $result->token()};
                    $c = is_array($c) ? $c : [$c];
                    $mutation = TokenStream::empty();
                    array_walk_recursive(
                        $c,
                        function($t) use($mutation) {
                            if ($t)
                                $mutation->push($t);
                        }
                    );
                    $cg->ts->inject($mutation);
                })
                ,
                any()
            )
        )
        ->parse($cg->ts);

        return $cg->ts;
    }

    protected function lookupCapture(Token $token) : string {
        $id = (string) $token;
        if (isset($this->lookup[$id]))
            $this->fail(self::E_LOOKUP, $id, $token->line());

        $this->lookup[$id] = true;

        return $id;
    }

    private function lookupParser(Token $token) : string {
        $identifier = (string) $token;
        $parser = '\Yay\\' . explode('·', $identifier)[1];

        if (! function_exists($parser))
            $this->fail(self::E_PARSER, $identifier, $token->line());

        return $parser;
    }

    private function lookupExpander(Token $token) : string {
        $identifier = (string) $token;
        $expander = '\Yay\Dsl\Expanders\\' . explode('·', $identifier)[1];

        if (! function_exists($expander))
            $this->fail(self::E_EXPANDER, $identifier, $token->line());

        return $expander;
    }

    private function layer(string $start, string $end, parser $parser) : Parser {
        return
            chain
            (
                token($start)
                ,
                rtoken('/^···(\w+)$/')->as('label')
                ,
                commit
                (
                    token($end)
                )
            )
            ->onCommit(function(Ast $result) use($parser) {
                $id = $this->lookupCapture($result->label);
                $this->parsers[] = (clone $parser)->as($id);
            });
    }
}
