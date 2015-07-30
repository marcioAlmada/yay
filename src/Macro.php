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
        E_EMPTY_BLOCK = "Empty macro %s on line %d.",
        E_BAD_CAPTURE = "Bad macro capture identifier '%s' on line %d.",
        E_BAD_EXPANSION = "Bad macro expansion identifier '%s' on line %d.",
        E_BAD_PARSER = "Bad macro parser identifier '%s' on line %d.",
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
        $crossover = $this->pattern->parse($ts);

        if (! ($crossover instanceof ast) || $crossover->isEmpty()) return;

        $expansion = $this->mutate($this->expansion, $crossover);
        $ts->inject($expansion);
        $ts->step(-1);
    }

    private function compilePattern(int $line, array $pattern) : parser {
        if(! $pattern) $this->fail(self::E_EMPTY_BLOCK, 'pattern', $line);

        repeat
        (
            either
            (
                rtoken('/^(T_[_\w]+)·(\w+)$/')
                    ->onCommit(function(Ast $result) {
                        $token = $result->token();
                        $id = $this->lookupCapture($token);
                        $type = $this->lookupTokenType($token);
                        $this->parsers[] = token($type)->as($id);
                    })
                ,
                (
                    clone
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
                $this->layer('{', '}', braces())
                ,
                $this->layer('[', ']', brackets())
                ,
                $this->layer('(', ')', parentheses())
                ,
                // TODO non delimited layer ↓
                // rtoken('/^···(\w+)$/')
                //     ->onCommit(function(Ast $result) {
                //     })
                // ,
                swallow
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

        // foreach ($this->pairs as $chars => $count)
        //     if ($count % 2)
        //         throw new YayException(
        //             "Unmatched pair of '{$chars}' on macro '{$pattern}'.");

        return optional(swallow(chain(...$this->parsers)));
    }

    private function compileExpansion(int $line, array $expansion) : TokenStream {
        if(! $expansion) $this->fail(self::E_EMPTY_BLOCK, 'expansion', $line);

        $ts = TokenStream::fromSlice($expansion);
        $ts->trim();

        repeat
        (
            either
            (
                chain
                (
                    rtoken('/^·\w+$/')->as('label')
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

    private function compileParser(array $result) : parser
    {
        $ast = $result['parser_type'];
        $type = (string) $ast;
        $fqn = __NAMESPACE__ . '\\' . explode('·', $type)[1];

        if (! function_exists($fqn))
            $this->fail(self::E_BAD_PARSER, $type, $ast->line());

        $args = $this->compileParserArgs($result['args']);
        $parser = $fqn(...$args);

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
                $arg = token($this->lookupTokenType($arg));
                break;
            default: // non T_STRING wordy token
                $arg = token($arg->type());
                break;
        }

        return $arg;
    }

    private function mutate(TokenStream $ts, Ast $crossover) : TokenStream {
        if ($this->constant) goto end;

        $ts = clone $ts;
        repeat
        (
            either
            (
                swallow
                (
                    chain
                    (
                        rtoken('/^·\w+$/')->as('label')
                        ,
                        operator('···')
                        ,
                        braces()->as('expansion')
                    )
                )
                ->onCommit(function(Ast $result)  use($ts, $crossover) {
                    $crossovers = $crossover->{(string) $result->label};
                    foreach (array_reverse($crossovers) as $context) {
                        $expansion = TokenStream::fromSlice($result->expansion);
                        if(! is_array($context)) $context = [$context];
                        $context = new Ast(
                            null, array_merge($context, $crossover->all()[0]));
                        $mutation = $this->mutate($expansion, $context);
                        $ts->inject($mutation);
                    }
                })
                ,
                swallow
                (
                    chain
                    (
                        token(T_NS_SEPARATOR)
                        ,
                        token(T_STRING, '·n')
                    )
                )
                ->onCommit(function(Ast $result) use($ts, $crossover) {
                    $ts->push(new Token(T_WHITESPACE, PHP_EOL));
                })
                ,
                swallow
                (
                    rtoken('/^T_\w+·\w+$/')
                )
                ->onCommit(function(Ast $result) use($ts, $crossover) {
                    $mutation = $crossover->{(string) $result->token()};
                    $ts->inject(TokenStream::fromSequence($mutation));
                })
                ,
                swallow
                (
                    rtoken('/^·\w+|···\w+$/')
                )
                ->onCommit(function(Ast $result) use ($ts, $crossover) {
                    $c = $crossover->{(string) $result->token()};
                    $c = is_array($c) ? $c : [$c];
                    $mutation = TokenStream::empty();
                    array_walk_recursive(
                        $c,
                        function($t) use($mutation) {
                            if ($t)
                                $mutation->push($t);
                        }
                    );
                    $ts->inject($mutation);
                })
                ,
                any()
            )
        )
        ->parse($ts);

        end:

        return $ts;
    }

    protected function lookupCapture(Token $token) : string {
        $id = (string) $token;
        if (isset($this->lookup[$id]))
            $this->fail(self::E_LOOKUP, $id, $token->line());

        $this->lookup[$id] = true;

        return $id;
    }

    private function layer(string $start, string $end, parser $parser) : parser {
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
