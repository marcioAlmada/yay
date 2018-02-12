<?php declare(strict_types=1);

namespace Yay;

class Pattern extends MacroMember implements PatternInterface {

    const
        E_BAD_CAPTURE = "Bad macro capture identifier '%s' on line %d.",
        E_BAD_DOMINANCE = "Bad dominant macro marker '·' offset %d on line %d.",
        E_BAD_PARSER_NAME = "Bad macro parser identifier '%s' on line %d.",
        E_BAD_TOKEN_TYPE = "Undefined token type '%s' on line %d.",
        E_EMPTY_PATTERN = "Empty macro pattern on line %d.",
        E_IDENTIFIER_REDEFINITION = "Redefinition of macro capture identifier '%s' on line %d."
    ;

    protected
        $scope,
        $pattern,
        $specificity = 0,
        $dominance = 0
    ;

    function __construct(int $line, array $pattern, Map $tags, Map $scope) {
        if (0 === \count($pattern))
            $this->fail(self::E_EMPTY_PATTERN, $line);

        $this->scope = $scope;
        $this->pattern = $this->compile($pattern);

        if ($tags->contains('·optimize')) $this->pattern = $this->pattern->optimize();
    }

    function match(TokenStream $ts) {
        return $this->pattern->parse($ts);
    }

    function specificity() : int {
        return $this->specificity;
    }

    function expected() : Expected {
        return $this->pattern->expected();
    }

    private function compile(array $tokens) {

        $cg = (object)[
            'ts' => TokenStream::fromSlice($tokens),
            'parsers' => [],
        ];

        traverse
        (
            rtoken('/^(T_\w+)·(\w+)$/')
                ->onCommit(function(Ast $result) use($cg) {
                    $token = $result->token();
                    $identifier = $this->lookupCapture($token);
                    $type = $this->lookupTokenType($token);
                    $cg->parsers[] = token($type)->as($identifier);
                })
            ,
            (
                $parser = chain
                (
                    rtoken('/^·\w+$/')->as('type')
                    ,
                    token('(')
                    ,
                    optional
                    (
                        ls
                        (
                            either
                            (
                                pointer
                                (
                                    $parser // recursion !!!
                                )
                                ->as('parser')
                                ,
                                chain
                                (
                                    token(T_FUNCTION)
                                    ,
                                    parentheses()->as('args')
                                    ,
                                    braces()->as('body')
                                )
                                ->as('function')
                                ,
                                string()->as('string')
                                ,
                                rtoken('/^T_\w+·\w+$/')->as('token')
                                ,
                                rtoken('/^T_\w+$/')->as('constant')
                                ,
                                rtoken('/^·this$/')->as('this')
                                ,
                                label()->as('label')
                                ,
                                between
                                (
                                    token(T_CONSTANT_ENCAPSED_STRING, "''"),
                                    any(),
                                    token(T_CONSTANT_ENCAPSED_STRING, "''")
                                )
                                ->as('literal')
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
                        rtoken('/^·\w+$/')->as('label'), null
                    )
                )
            )
            ->onCommit(function(Ast $result) use($cg) {
                $cg->parsers[] = $this->compileParser($result);
            })
            ,
            // handles {···layer}
            $this->layer('{', '}', braces(), $cg)
            ,
            // handles [···layer]
            $this->layer('[', ']', brackets(), $cg)
            ,
            // handles (···layer)
            $this->layer('(', ')', parentheses(), $cg)
            ,
            // handles  non delimited ···layer
            rtoken('/^···(\w+)$/')
                ->onCommit(function(Ast $result) use($cg) {
                    $identifier = $this->lookupCapture($result->token());
                    $cg->parsers[] = layer()->as($identifier);
                })
            ,
            token(T_STRING, '·')
                ->onCommit(function(Ast $result) use ($cg) {
                    $offset = \count($cg->parsers);
                    if (0 !== $this->dominance || 0 === $offset) {
                        $this->fail(self::E_BAD_DOMINANCE, $offset, $result->token()->line());
                    }
                    $this->dominance = $offset;
                })
            ,
            rtoken('/·/')
                ->onCommit(function(Ast $result) {
                    $token = $result->token();
                    $this->fail(self::E_BAD_CAPTURE, $token, $token->line());
                })
            ,
            any()
                ->onCommit(function(Ast $result) use($cg) {
                    $cg->parsers[] = token($result->token());
                })
        )
        ->parse($cg->ts);

        $this->specificity = \count($cg->parsers);

        // check if macro dominance '·' is last token
        if ($this->dominance === $this->specificity)
            $this->fail(self::E_BAD_DOMINANCE, $this->dominance, $cg->ts->last()->line());

        if ($this->specificity > 1) {
            if (0 === $this->dominance) {
                $pattern = chain(...$cg->parsers);
            }
            else {
                /*
                  dominat macros are partially wrapped in commit()s and dominance
                  is the offset used as the 'event horizon' point... once the entry
                  point is matched, there is no way back and a parser error arises
                */
                $prefix = array_slice($cg->parsers, 0, $this->dominance);
                $suffix = array_slice($cg->parsers, $this->dominance);
                $pattern = chain(...array_merge($prefix, array_map(commit::class, $suffix)));
            }
        }
        else {
            /*
              micro optimization to save one function call for every token on the subject
              token stream whenever the macro pattern consists of a single parser
            */
            $pattern = $cg->parsers[0];
        }

        return $pattern;
    }

    private function layer(string $start, string $end, Parser $parser, $cg) : Parser {
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
            ->onCommit(function(Ast $result) use($parser, $cg) {
                $identifier = $this->lookupCapture($result->label);
                $cg->parsers[] = (clone $parser)->as($identifier);
            });
    }

    protected function lookupTokenType(Token $token) : int {
        $type = explode('·', (string) $token)[0];
        if (! defined($type))
            $this->fail(self::E_BAD_TOKEN_TYPE, $type, $token->line());

        return constant($type);
    }

    private function lookupCapture(Token $token) : string {
        $identifier = (string) $token;

        if ($identifier === '·_') return '';

        if ($this->scope->contains($identifier))
            $this->fail(self::E_IDENTIFIER_REDEFINITION, $identifier, $token->line());

        $this->scope->add($identifier);

        return $identifier;
    }

    private function lookupParser(Token $token) : callable {
        $identifier = (string) $token;
        $parser = '\Yay\\' . explode('·', $identifier)[1];

        if ($identifier === '·_')
            return function(){ return midrule(function(){ return new Ast; }); };

        if (! function_exists($parser))
            $this->fail(self::E_BAD_PARSER_NAME, $identifier, $token->line());

        return $parser;
    }

    protected function compileParser(Ast $ast) : Parser {
        $parser = $this->lookupParser($ast->{'* type'}->token());
        $args = $this->compileParserArgs($ast->{'* args'});
        $parser = $parser(...$args);
        if (($label = $ast->{'label'}) && $ast->{'* type'}->token() != '·_')
            $parser->as($this->lookupCapture($label));

        return $parser;
    }

    protected function compileParserArgs(Ast $args) : array {
        $compiled = [];

        foreach ($args->list() as $arg) switch ((string) $arg->label()) {
            case 'this':
                $compiled[] = pointer($this->pattern);
                break;
            case 'token':
                $token = $arg->token();
                $type = $this->lookupTokenType($token);
                $label = $this->lookupCapture($token);
                $compiled[] = token($type)->as($label);
                break;
            case 'label':
            case 'literal':
                $compiled[] = token($arg->token());
                break;
            case 'parser':
                $compiled[] = $this->compileParser($arg);
                break;
            case 'string':
                $compiled[] = trim((string) $arg->token(), '"\'');
                break;
            case 'constant': // T_*
                $compiled[] = $this->lookupTokenType($arg->token());
                break;
            case 'function': // function(...){...}
                $compiled[] = $this->compileAnonymousFunctionArg($arg);
                break;
            default:
                $compiled = array_merge(
                    $compiled, $this->compileParserArgs($arg));
        }

        return $compiled;
    }

    private function compileAnonymousFunctionArg($arg) : \Closure {
        if ($arg instanceof Ast) {
            $arg = $arg->unwrap();
        }

        if (!is_array($arg)) {
            throw new InvalidArgumentException('$arg should be an array or instance of Yay\Ast');
        }

        $arglist = implode('', $arg['args']);
        $body = implode('', $arg['body']);
        $source = "<?php\nreturn static function({$arglist}){\n{$body}\n};";
        $file = sys_get_temp_dir() . '/yay-function-' . sha1($source);

        if (!is_readable($file))
            file_put_contents($file, $source);

        return include $file;
    }
}
