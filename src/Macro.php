<?php declare(strict_types=1);

namespace Yay;

use function Yay\DSL\Expanders\{ hygienize };

class Macro implements Directive {

    const
        PRETTY_PRINT =
                JSON_PRETTY_PRINT
            |   JSON_BIGINT_AS_STRING
            |   JSON_UNESCAPED_UNICODE
            |   JSON_UNESCAPED_SLASHES
    ;

    const
        E_TOKEN_TYPE = "Undefined token type '%s' on line %d.",
        E_EMPTY_PATTERN = "Empty macro pattern on line %d.",
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
        $cycle,
        $tags = [],
        $lookup = [],
        $parsers = [],
        $specificity = 0,
        $dominant = false,
        $constant = true,
        $unsafe = false
    ;

    private
        $id
    ;

    function __construct(int $line, array $tags, array $pattern, array $expansion, Cycle $cycle) {
        static $id = 0;
        $this->compileTags($tags);
        $this->pattern = $this->compilePattern($line, $pattern);
        if ($expansion)
            $this->expansion = $this->compileExpansion($line, $expansion);
        $this->id = $id++;
        $this->cycle = $cycle;
    }

    function id() : int {
        return $this->id;
    }

    function specificity() : int {
        return $this->specificity;
    }

    function apply(TokenStream $ts) {
        $from = $ts->index();
        $crossover = $this->pattern->parse($ts);

        if (null === $crossover || $crossover instanceof Error) return;

        if ($this->expansion) {
            // infer blue context from matched tokens
            $context = new BlueContext;
            $tokens = $crossover->all();
            array_walk_recursive($tokens, function(Token $token) use ($context) {
                $context->inherit($token->context());
            });

            if (! $this->hasTag('·recursion'))
                if ($context->contains($this->id())) return; // already expanded

            $context->add($this->id());
            $ts->unskip(...TokenStream::SKIPPABLE);
            $to = $ts->index();
            $ts->extract($from, $to);

            $expansion = $this->mutate($this->expansion, $crossover);
            $this->cycle->next();

            // paint blue context of expasion tokens
            $expansion->each(function(Token $token) use ($context) {
                $token->context()->inherit($context);
            });

            $ts->inject($expansion, $from);
        }
        else {
            $ts->unskip(...TokenStream::SKIPPABLE);
            $ts->skip(T_WHITESPACE);
            $to = $ts->index();
            $ts->extract($from, $to);
        }
    }

    private function hasTag(string $tag) : bool {
        return isset($this->tags[$tag]);
    }

    private function compileTags(array $tags)/* : void */ {
        foreach ($tags as $tag)
            $this->tags[(string) $tag] = true;
    }

    private function compilePattern(int $line, array $pattern) : Parser {
        if(! $pattern) $this->fail(self::E_EMPTY_PATTERN, $line);

        traverse
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
                                    ->as('parser')
                                    ,
                                    token(T_CONSTANT_ENCAPSED_STRING)->as('string')
                                    ,
                                    rtoken('/^T_\w+·\w+$/')->as('token')
                                    ,
                                    rtoken('/^T_\w+$/')->as('constant')
                                    ,
                                    rtoken('/^\w+$/')->as('word')
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
        $ts = TokenStream::fromSlice($expansion);
        $ts->trim();

        traverse
        (
            either
            (
                token(T_VARIABLE)
                    ->onCommit(function() { $this->unsafe = true; })
                ,
                chain
                (
                    rtoken('/^·\w+$/')->as('expander')
                    ,
                    parentheses()->as('args')
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
        foreach ($args as $type => $arg) switch ((string) $type) {
            case 'token':
                $type = $this->lookupTokenType($arg);
                $label = $this->lookupCapture($arg);
                $compiled[] = token($type)->as($label);
                break;
            case 'word':
                $compiled[] = token($arg);
                break;
            case 'parser':
                $compiled[] = $this->compileParser($arg);
                break;
            case 'string':
                $compiled[] = trim((string) $arg, '"\'');
                break;
            case 'constant': // T_*
                $compiled[] = $this->lookupTokenType($arg);
                break;
            default:
                $compiled = array_merge(
                    $compiled, $this->compileParserArgs($arg));
        }

        return $compiled;
    }
    private function mutate(TokenStream $ts, Ast $crossover) : TokenStream {

        $cg = (object) [
            'ts' => clone $ts,
            'crossover' => $crossover,
            // 'frames' => [] // @TODO switch frames instead of merging context
        ];

        if ($this->unsafe && !$this->hasTag('·dirty')) hygienize($cg->ts, $this->cycle->id());

        if ($this->constant) return $cg->ts;

        traverse
        (
            either
            (
                consume
                (
                    chain
                    (
                        rtoken('/^·\w+$/')->as('expander')
                        ,
                        parentheses()->as('args')
                    )
                )
                ->onCommit(function(Ast $result) use ($cg) {
                    $expander = $this->lookupExpander($result->expander);
                    $args = [];
                    foreach ($result->args as $arg) {
                        if ($arg instanceof Token) {
                            $key = (string) $arg;
                            if (preg_match('/^·\w+|T_\w+·\w+|···\w+$/', $key)) {
                                $arg = $cg->crossover->{$key};
                            }
                        }

                        if (is_array($arg))
                            array_push($args, ...$arg);
                        else
                            $args[] = $arg;
                    }
                    $mutation = $expander(TokenStream::fromSlice($args), $this->cycle->id());
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
                    $mutation = TokenStream::fromEmpty();
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

        $cg->ts->reset();

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

    private function layer(string $start, string $end, Parser $parser) : Parser {
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
                $this->parsers[] = $parser->as($id);
            });
    }

    private function lookupTokenType(Token $token) : int {
        $type = explode('·', (string) $token)[0];
        if (! defined($type))
            $this->fail(self::E_TOKEN_TYPE, $type, $token->line());

        return constant($type);
    }

    private function fail(string $error, ...$args) {
        throw new YayException(sprintf($error, ...$args));
    }
}
