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
        $unsafe = false,
        $cloaked = false
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

    private function compilePattern(int $line, array $tokens) : Parser {
        if(! $tokens) $this->fail(self::E_EMPTY_PATTERN, $line);

        $ts = TokenStream::fromSlice($tokens);

        traverse
        (
            either
            (
                consume
                (
                    chain
                    (
                        token(T_NS_SEPARATOR)
                        ,
                        token(T_NS_SEPARATOR)
                        ,
                        parentheses()->as('cloaked')
                    )
                )
                ->onCommit(function(Ast $result) use($ts) {
                    $ts->inject(TokenStream::fromSequence(...$result->cloaked));
                    $ts->skip(...TokenStream::SKIPPABLE);
                })
                ,
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
                                    string()->as('string')
                                    ,
                                    rtoken('/^T_\w+·\w+$/')->as('token')
                                    ,
                                    rtoken('/^T_\w+$/')->as('constant')
                                    ,
                                    word()->as('word')
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
        ->parse($ts);

        $this->specificity = \count($this->parsers);

        if ($this->specificity > 1)
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
                consume
                (
                    chain
                    (
                        token(T_NS_SEPARATOR)
                        ,
                        token(T_NS_SEPARATOR)
                        ,
                        parentheses()->as('cloaked')
                    )
                )
                ->onCommit(function(Ast $result) use ($ts){
                    $ts->inject(
                        TokenStream::fromSequence(
                            new Token(
                                Token::CLOAKED,
                                implode('', $result->cloaked)
                            )
                        )
                    );
                    $this->cloaked = true;
                })
                ,
                token(Token::CLOAKED)
                ,
                either
                (
                    token(T_VARIABLE)
                    ,
                    chain(identifier(), token(':'))
                    ,
                    chain(token(T_GOTO), identifier())
                )
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
                rtoken('/^(T_\w+·\w+|·\w+|···\w+)$/')
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
        foreach ($args as $label => $arg) switch ((string) $label) {
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
    private function mutate(TokenStream $ts, Ast $context) : TokenStream {

        $cg = (object) [
            'ts' => clone $ts,
            'context' => $context
        ];

        if ($this->unsafe && !$this->hasTag('·unsafe')) hygienize($cg->ts, $this->cycle->id());

        if ($this->constant) return $cg->ts;

        traverse
        (
            either
            (
                token(Token::CLOAKED)
                ,
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
                                $arg = $cg->context->{$key};
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
                    $index = (string) $result->label;
                    $context = $cg->context->{$index};

                    if ($context === null) {
                        $this->fail(
                            self::E_EXPANSION,
                            $index,
                            $result->label->line(),
                            json_encode (
                                array_keys($cg->context->all()[0]),
                                self::PRETTY_PRINT
                            )
                        );
                    }

                    $expansion = TokenStream::fromSlice($result->expansion);

                    // normalize single context
                    if (array_values($context) !== $context) $context = [$context];

                    foreach (array_reverse($context) as $i => $subContext) {
                        $mutation = $this->mutate(
                            $expansion,
                            (new Ast(null, $subContext))
                                ->withParent($cg->context)
                        );
                        $cg->ts->inject($mutation);
                    }
                })
                ,
                consume
                (
                    rtoken('/^(T_\w+·\w+|·\w+|···\w+)$/')
                )
                ->onCommit(function(Ast $result) use ($cg) {
                    $expansion = $cg->context->{(string) $result->token()};

                    if ($expansion instanceof Token) {
                        $cg->ts->inject(TokenStream::fromSequence($expansion));
                    }
                    elseif (is_array($expansion) && \count($expansion)) {
                        $tokens = [];
                        array_walk_recursive(
                            $expansion,
                            function(Token $token) use(&$tokens) {
                                $tokens[] = $token;
                            }
                        );
                        $cg->ts->inject(TokenStream::fromSlice($tokens));
                    }
                })
                ,
                any()
            )
        )
        ->parse($cg->ts);

        $cg->ts->reset();

        if ($this->cloaked) {
            traverse
            (
                either
                (
                    consume
                    (
                        token(Token::CLOAKED)
                    )
                    ->onCommit(function(Ast $result) use($cg) {
                        $cg->ts->inject(
                            TokenStream::fromSourceWithoutOpenTag(
                                (string) $result->token()
                            )
                        );
                    })
                    ,
                    any()
                )
            )
            ->parse($cg->ts);

            $cg->ts->reset();
        }

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
                $this->parsers[] = (clone $parser)->as($id);
            });
    }

    private function lookupTokenType(Token $token) : int {
        $type = explode('·', (string) $token)[0];
        if (! defined($type))
            $this->fail(self::E_TOKEN_TYPE, $type, $token->line());

        return constant($type);
    }

    private function fail(string $error, ...$args) {
        throw new YayParseError(sprintf($error, ...$args));
    }
}
