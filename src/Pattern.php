<?php declare(strict_types=1);

namespace Yay;

class Pattern extends MacroMember implements PatternInterface {

    const
        E_BAD_CAPTURE = "Bad macro capture identifier '%s' on line %d.",
        E_BAD_DOMINANCE = "Bad dominant macro marker '" . YAY_PATTERN_COMMIT . "' offset %d on line %d.",
        E_BAD_PARSER_NAME = "Bad macro parser identifier '%s' on line %d.",
        E_BAD_TOKEN_TYPE = "Undefined token type '%s' on line %d.",
        E_EMPTY_PATTERN = "Empty macro pattern on line %d.",
        E_IDENTIFIER_REDEFINITION = "Redefinition of macro capture identifier '%s' on line %d."
    ;

    const
        NULL_LABEL = '_'
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

        if ($tags->contains('optimize')) $this->pattern = $this->pattern->optimize();
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

        // cg is the compiler globals
        $cg = (object)[
            'ts' => TokenStream::fromSlice($tokens),
            'parsers' => [],
        ];

        /*
            Here we traverse the macro declaration token stream and look for
            declared ast node matchers under the preprocessor sigil `$(...)`
         */
        traverse
        (
            /*
                Matches:
                    $(T_STRING)
                Compiles to:
                    token(T_STRING)

                Matches:
                    $(T_STRING as foo)
                Compiles to:
                    token(T_STRING)->as('foo')
             */
            sigil(token_constant(), optional(alias()))
                ->onCommit(function(Ast $result) use($cg) {
                    $token = $this->compileTokenConstant($result->{'* token_constant'});
                    $alias = $this->compileAlias($result->{'* alias'});
                    $cg->parsers[] = token($token)->as($alias);
                })
            ,
            // Matches complex parser combinator declarations
            sigil(parsec())
                ->onCommit(function(Ast $result) use($cg) {
                    $cg->parsers[] = $this->compileParser($result->{'* parsec'});
                })
            ,
            /*
                Matches:
                    $({...})
                Compiles to:
                    braces()

                Matches:
                    $({...} as foo)
                Compiles to:
                    braces()->as('foo')
            */
            sigil($this->layer('{', '}', braces(), $cg))
            ,
            /*
                Matches:
                    $([...])
                Compiles to:
                    brackets()

                Matches:
                    $([...] as foo)
                Compiles to:
                    brackets()->as('foo')
            */
            sigil($this->layer('[', ']', brackets(), $cg))
            ,
            /*
                Matches:
                    $((...))
                Compiles to:
                    parentheses()

                Matches:
                    $((...) as foo)
                Compiles to:
                    parentheses()->as('foo')
            */
            sigil($this->layer('(', ')', parentheses(), $cg))
            ,
            /*
                Matches:
                    $(...)
                Compiles to:
                    layer()

                Matches:
                    $(... as foo)
                Compiles to:
                    layer()->as('foo')
            */
            sigil(token(T_ELLIPSIS), optional(alias()))
                ->onCommit(function(Ast $result) use($cg) {
                    $alias = $this->compileAlias($result->{'* alias'});
                    $cg->parsers[] = layer()->as($alias);
                })
            ,
            /*
                Matches:
                    $! <the rest of the pattern>
                Compiles to:
                    commit(<the rest of the pattern>)

                > Causes the pattern after $ to throw a preprocessor error in case the pattern is
                not fully matched. The normal behavior is to silent failure and backtrack. This is
                useful to introduce first class language features with elegant syntax errors within
                DSLs
             */
            pattern_commit()
                ->onCommit(function(Ast $result) use ($cg) {
                    $offset = \count($cg->parsers);
                    if (0 !== $this->dominance || 0 === $offset) {
                        $this->fail(self::E_BAD_DOMINANCE, $offset, $result->tokens()[0]->line());
                    }
                    $this->dominance = $offset;
                })
            ,
            /*
                Matches:
                    Possible orphaned $()

                > Causes a preprocessor error pointing a macro syntax error
             */
            sigil(layer())
                ->onCommit(function(Ast $result) use ($cg) {
                    $this->fail(self::E_BAD_CAPTURE, $result->implode(), $result->{'sigil'}[0]->line());
                })
            ,
            /*
                Matches:
                    Anything the preprocessor is not aware of
                Compiles to:
                    A literal pattern of whatever was matched
             */
            any()
                ->onCommit(function(Ast $result) use($cg) {
                    $cg->parsers[] = token($result->token());
                })
        )
        ->parse($cg->ts);

        $this->specificity = \count($cg->parsers);

        // check if macro dominance '$' is last token
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
                token(T_ELLIPSIS)
                ,
                commit(token($end))
                ,
                alias()
            )
            ->onCommit(function(Ast $result) use($parser, $cg) {
                $identifier = $this->compileAlias($result->{'* alias'});
                $cg->parsers[] = (clone $parser)->as($identifier);
            });
    }

    protected function compileTokenConstant(Ast $constant) : int {
        $type = (string) $constant->token();
        if (! defined($type))
            $this->fail(self::E_BAD_TOKEN_TYPE, $type, $constant->token()->line());

        return constant($type);
    }

    private function compileAlias(Ast $alias) : string {
        $identifier = $alias->{'name'} ? (string) $alias->{'* name'}->token() : '_';

        if ($identifier === self::NULL_LABEL) return '';

        if ($this->scope->contains($identifier))
            $this->fail(self::E_IDENTIFIER_REDEFINITION, $identifier, $alias->{'* name'}->token()->line());

        $this->scope->add($identifier);

        return $identifier;
    }

    protected function compileArray(Ast $array) : array {
        $compiled = [];
        foreach ($array->{'* values'}->list() as $valueNode) {
            $key = count($compiled);
            $value = $valueNode->{'* value'};

            if ($valueNode->{'key_value_pair'}) {
                $value = $valueNode->{'* key_value_pair value'};
                $type = key($valueNode->{'* key_value_pair key'}->array());
                $key = $valueNode->{"* key_value_pair key {$type}"};
                switch ($type) {
                  case 'string':
                      $key =  trim((string) $key->token(), '"\'');
                      break;
                  case 'int':
                      $key =  (int) $key->token()->value();
                }
            }

            $type = key($value->array());
            $value = $value->{"* {$type}"};
            switch ($type) {
              case 'string':
                  $value =  trim((string) $value->token(), '"\'');
                  break;
              case 'int':
                  $value =  (int) $value->token()->value();
                  break;
              case 'array':
                  $value = $this->compileArray($value);
            }

            $compiled[$key] = $value;
        }

        return $compiled;
    }

    protected function compileParser(Ast $ast) : Parser {
        $parser = $this->compileCallable('\Yay\\', $ast->{'* type'}, self::E_BAD_PARSER_NAME);
        $args = $ast->{'* args'}->isEmpty() ? [] : $this->compileParserArgs($ast->{'* args'});
        $parser = $parser(...$args);
        $alias = $this->compileAlias($ast->{'* alias'});
        $parser->as((string) $alias);

        return $parser;
    }

    protected function compileParserArgs(Ast $args) : array {
        $compiled = [];
        foreach ($args->list() as $ast) {
            $type = key($ast->array());
            $arg = $ast->{"* {$type}"};
            switch ($type) {
                case 'this':
                    $compiled[] = pointer($this->pattern);
                    break;
                case 'named_token_constant':
                    $token = $this->compileTokenConstant($arg->{'* token_constant'});
                    $alias = $this->compileAlias($arg->{'* alias'});
                    $compiled[] = token($token)->as($alias);
                    break;
                case 'token_constant':
                    $compiled[] = $this->compileTokenConstant($arg);
                    break;
                case 'literal':
                    $compiled[] = token($arg->token());
                    break;
                case 'parsec':
                    $compiled[] = $this->compileParser($arg);
                    break;
                case 'string':
                    $compiled[] = trim((string) $arg->token(), '"\'');
                    break;
                case 'array':
                    $compiled[] = $this->compileArray($arg);
                    break;
                case 'function': // function(...){...}
                    $compiled[] = new AnonymousFunction($arg);
                    break;
                default:
                    assert(false, "Unknown parser argument type '{$type}'");
            }
        }

        return $compiled;
    }
}
