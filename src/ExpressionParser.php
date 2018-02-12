<?php declare(strict_types=1);

namespace Yay;

class ExpressionParser extends Parser
{
    const
        // arity flags
        ARITY = 0x111000,
        ARITY_UNARY = 0x100000,
        ARITY_BINARY = 0x010000,
        ARITY_TERNARY = 0x001000,
        // associativity flags
        ASSOC = 0x000111,
        ASSOC_NONE = 0x000100,
        ASSOC_LEFT = 0x000010,
        ASSOC_RIGHT = 0x000001,
        // positional flags
        POS_PREFIX = self::ARITY_UNARY|self::ASSOC_NONE|self::ASSOC_RIGHT,
        POS_INFIX = self::ARITY_BINARY|self::ARITY_TERNARY|self::ASSOC_LEFT|self::ASSOC_RIGHT|self::ASSOC_NONE,
        POS_SUFFIX = self::ARITY_UNARY|self::ASSOC_LEFT
    ;

    private
        $parser,
        $id = '',
        $parameters = [
            'expressions' => [],
            'operators' => [
                self::POS_PREFIX => [],
                self::POS_INFIX => [],
                self::POS_SUFFIX => [],
            ]
        ],
        $precedence = 0
    ;

    function __construct()
    {
        parent::__construct(__CLASS__, $this->rebuildParser());
    }

    function parser(TokenStream $ts) /*: Result|null*/
    {
        $result = $this->rebuildParser()->parse($ts);

        if ($result instanceof Ast) $result->as($this->label);

        return $result;
    }

    function expected() : Expected
    {
        return $this->stack[0]->expected();
    }

    function isFallible() : bool
    {
        return $this->stack[0]->isFallible();
    }

    private function rebuildParser() : Parser
    {
        if ($this->id === count($this->parameters, COUNT_RECURSIVE)) return $this->stack[0];

        // pointers {
        $variable = pointer($variable)->as('variable_pointer');
        $expression = pointer($expression)->as('expression_pointer');
        $expression_list = pointer($expression_list)->as('expression_list_pointer');
        $simple_variable = pointer($simple_variable)->as('simple_variable_pointer');
        $array_value_list = pointer($array_value_list)->as('array_value_list_pointer');
        $dereferenced = pointer($dereferenced)->as('dereferenced_pointer');
        $class_name = pointer($class_name)->as('class_name_pointer');
        $dereferencable = pointer($dereferencable)->as('dereferencable_pointer');
        $arguments = pointer($arguments)->as('arguments_pointer');
        // }

        if (null === $this->stack[0]) {
            $this->addOperator(self::ARITY_UNARY|self::ASSOC_RIGHT, token(T_INCLUDE), token(T_INCLUDE_ONCE), token(T_REQUIRE), token(T_REQUIRE_ONCE));
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, chain(token(T_LOGICAL_OR), optional(indentation())));
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, chain(token(T_LOGICAL_XOR), optional(indentation())));
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, chain(token(T_LOGICAL_AND), optional(indentation())));
            $this->addOperator(self::ARITY_UNARY|self::ASSOC_RIGHT, token(T_PRINT));
            // $this->addOperator(self::ARITY_UNARY|self::ASSOC_RIGHT, chain(token(T_YIELD), optional(indentation())));
            $this->addOperator(self::ARITY_UNARY|self::ASSOC_RIGHT, chain(token(T_YIELD_FROM), optional(indentation())));
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_RIGHT, chain(token('='), token('&')), '=', T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL, T_MOD_EQUAL, T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_POW_EQUAL);
            $this->addOperator(self::ARITY_TERNARY|self::ASSOC_LEFT, chain(token('?'), optional($expression), token(':'))); // ternary, but for precedence climbing it's binary like???
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_RIGHT, T_COALESCE);
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, chain(token(T_BOOLEAN_OR), optional(indentation())));
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, chain(token(T_BOOLEAN_AND), optional(indentation())));
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, '|');
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, '^');
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, '&');
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_NONE, T_IS_EQUAL, T_IS_NOT_EQUAL, T_IS_IDENTICAL, T_IS_NOT_IDENTICAL);
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_NONE, '<', T_IS_SMALLER_OR_EQUAL, '>', T_IS_GREATER_OR_EQUAL, T_SPACESHIP);
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, T_SL, T_SR);
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, '+', '-', '.');
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_LEFT, '*', '/', '%');
            $this->addOperator(self::ARITY_UNARY|self::ASSOC_RIGHT, '!');
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_NONE, chain(token(T_INSTANCEOF), optional(indentation())));
            $this->addOperator(self::ARITY_UNARY|self::ASSOC_RIGHT, '+', '-', '~', T_INC, T_DEC, T_INT_CAST, T_DOUBLE_CAST, T_STRING_CAST, T_ARRAY_CAST, T_OBJECT_CAST, T_BOOL_CAST, T_UNSET_CAST, '@');
            $this->addOperator(self::ARITY_BINARY|self::ASSOC_RIGHT, T_POW);
            $this->addOperator(self::ARITY_UNARY|self::ASSOC_NONE, chain(token(T_NEW), optional(indentation())), chain(token(T_CLONE), optional(indentation())));
            $this->addOperator(self::ARITY_UNARY|self::ASSOC_LEFT, T_INC, T_DEC);
        }

        $namespace_name = ls(token(T_STRING), token(T_NS_SEPARATOR), LS_KEEP_DELIMITER)->as('namespace_name');

        $name = either(...[$namespace_name, chain(token(T_NAMESPACE), token(T_NS_SEPARATOR), $namespace_name), chain(token(T_NS_SEPARATOR), $namespace_name),])->as('name');

        $new_variable = either(...[$variable, chain($class_name, optional($dereferenced)),])->as('new_variable');

        $class_name = either(...[$name, token(T_STATIC),])->as('class_name');

        $class_name_reference = either(...[$class_name, $new_variable,])->as('class_name_reference');

        $identifier = rtoken('/^\w+$/');

        $constant = either(...[
            chain($class_name, token(T_DOUBLE_COLON), $identifier),
            $name,
        ]);

        $arguments = chain(
            token('('),
            optional(lst(either(chain(token(T_ELLIPSIS), $expression), $expression), token(','), LS_KEEP_DELIMITER))->as('argument'),
            token(')')
        )->as('arguments');

        $expression_list = ls($expression, token(','), LS_KEEP_DELIMITER)->as('expression_list');

        $array_value = chain($expression, optional(chain(token(T_DOUBLE_ARROW), $expression)))->as('array_value');

        $array_value_list = lst($array_value, token(','), LS_KEEP_DELIMITER)->as('array_value_list');

        $array = either(...[
            chain(token('['), optional($array_value_list), token(']')),
            chain(token(T_ARRAY), token('('), optional($array_value_list), token(')')),
        ])->as('array');

        $dereferencable_scalar = either(...[
            $constant,
            $array,
            token(T_CONSTANT_ENCAPSED_STRING),
        ])->as('dereferencable_scalar');

        $anonymous_class = chain(
            token(T_CLASS),
            optional($arguments),
            optional(chain(token(T_EXTENDS), $name)),
            optional(chain(token(T_IMPLEMENTS), ls($name, token(','), LS_KEEP_DELIMITER))),
            braces()
        );

        $anonymous_function = chain(...[
            token(T_FUNCTION),
            optional(token('&'))->as('returns_reference'),
            token('('), layer(), token(')')->as('arguments'),
            optional(chain(
                token(T_USE),
                token('('),
                ls(
                    either(chain(token('&'),token(T_VARIABLE)), token(T_VARIABLE)),
                    token(','),
                    LS_KEEP_DELIMITER
                ),
                token(')')
            ))->as('lexical_vars'),
            optional(chain(token(':'), $name))->as('return_type'),
            token('{'), layer(), token('}')->as('body')
        ]);

        $static_anonymous_function = chain(...[
            token(T_STATIC),
            $anonymous_function,
        ]);

        $static_member = either(...[
            chain($class_name, token(T_PAAMAYIM_NEKUDOTAYIM), $simple_variable),
        ]);

        $dereferencable = either(...[
            $dereferencable_scalar,
            $anonymous_class,
            $anonymous_function,
            $static_anonymous_function,
            $static_member,
        ]);

        $encaps_var_offset = either(...[
            token(T_STRING),
            chain(token('-'), token(T_NUM_STRING)),
            token(T_NUM_STRING),
            token(T_VARIABLE),
        ]);

        $encaps_var = either(...[
            chain(token(T_VARIABLE), optional(either(...[
                chain(token(T_OBJECT_OPERATOR), token(T_STRING)),
                chain(token('['), $encaps_var_offset, token(']')),
            ]))),
            chain(token(T_DOLLAR_OPEN_CURLY_BRACES), either(...[
                chain(token(T_STRING_VARNAME), token('}')),
                chain(token(T_STRING_VARNAME), token('['), $expression, token(']'), token('}')),
                chain($expression, token('}')),
            ])),
            chain(token(T_CURLY_OPEN), $variable, token('}')),
        ]);

        $encaps_list = repeat(either(...[
            token(T_ENCAPSED_AND_WHITESPACE),
            $encaps_var
        ]));

        $scalar = either(...[
            token(T_LNUMBER),
            token(T_DNUMBER),
            token(T_LINE),
            token(T_FILE),
            token(T_DIR),
            token(T_TRAIT_C),
            token(T_METHOD_C),
            token(T_FUNC_C),
            token(T_NS_C),
            token(T_CLASS_C),
            chain(token('"'), $encaps_list, token('"')),
            chain(token(T_START_HEREDOC), either(...[
                chain(token(T_ENCAPSED_AND_WHITESPACE), token(T_END_HEREDOC)),
                chain(token(T_END_HEREDOC)),
                chain($encaps_list, token(T_END_HEREDOC)),
            ])),
            $dereferencable_scalar,
        ])
        ->as('scalar');

        $simple_variable = either(...[
            token(T_VARIABLE),
            chain(token('$'), $simple_variable)->as('variable_variable'),
            chain(token('$'), token('{'), $expression, token('}'))->as('variable_variable'),
        ])
        ->as('simple_variable');

        $property_name = either(...[
            token(T_STRING),
            chain(token('{'), $expression, token('}')),
            $simple_variable,
        ])->as('property_name');

        $dereferenced = either(...[
            chain(token(T_DOUBLE_COLON), either($simple_variable, $property_name), optional($arguments), optional($dereferenced)),
            chain(token(T_OBJECT_OPERATOR), $property_name, optional($arguments), optional($dereferenced)),
            chain(token('['), token(']'), optional($dereferenced)),
            chain(token('['), $expression, token(']'), optional($dereferenced)),
            chain(token('{'), $expression, token('}'), optional($dereferenced)),
            chain($arguments, optional($dereferenced)),
        ])
        ->as('dereferenced');

        $variable = either(...[
            chain(token('('), $expression, token(')'), optional($dereferenced)),
            chain($dereferencable, optional($dereferenced)),
            chain($simple_variable, optional($dereferenced)),
        ])->as('variable');

        $backticks_expr = optional(either(...[
            token(T_ENCAPSED_AND_WHITESPACE),
            $encaps_list,
        ]))->as('backticks_expr');

        $internal_functions_in_yacc = either(...[
            chain(token(T_ISSET), token('('), $expression_list, optional(token(',')), token(')')),
            chain(token(T_EMPTY), token('('), $expression, token(')')),
            chain(token(T_EVAL), token('('), $expression, token(')')),
        ])->as('internal_functions_in_yacc');

        $expression = either(...$this->parameters['expressions'], ...[
            $internal_functions_in_yacc,
            chain(token(T_YIELD), optional(chain($expression, optional(chain(token(T_DOUBLE_ARROW), $expression))))),
            chain(token('`'), $backticks_expr, token('`')), // !
            chain(token(T_LIST), token('('), $array_value_list, token(')'), token('='), $expression), // !
            $variable,
            $scalar,
        ])
        ->as('simple_expression');


        $prefix = either(...$this->parameters['operators'][self::POS_PREFIX]);

        $infix = either(...$this->parameters['operators'][self::POS_INFIX]);

        $postfix = either(...$this->parameters['operators'][self::POS_SUFFIX]);

        $expression = (new class (__CLASS__, $prefix, $expression, $infix, $postfix) extends Parser {
            function parser($ts, $prefix, $expression, $infix, $postfix) {
                $buffer = [];
                $operators = [];

                tail_call: {
                    while(($operator = $prefix->parse($ts)) instanceof Ast) $operators[] = $operator;

                    if (! ($result = $expression->parse($ts)) instanceof Ast) return $result;

                    $buffer[] = $result;

                    if (($operator = $postfix->parse($ts)) instanceof Ast) $operators[] = $operator;

                    if (($operator = $infix->parse($ts)) instanceof Ast) {
                        while (
                            $operators
                            && end($operators)->meta()->get('precedence') >= $operator->meta()->get('precedence')
                            && $operator->meta()->get('associativity') !== ExpressionParser::ASSOC_RIGHT
                            && $buffer[] = array_pop($operators)
                        );

                        $operators[] = $operator;

                        goto tail_call;
                    }
                }

                $output = [];
                while($operators) $buffer[] = array_pop($operators);

                foreach ($buffer as $member) switch ($member->meta()->get('arity')) {
                    case ExpressionParser::ARITY_TERNARY:
                        $label = 'expression<ternary>';
                        assert(null !== ($right = array_pop($output)));
                        assert(null !== ($left = array_pop($output)));
                        $output[] = new Ast($label, ['left' => $left, 'middle' => $member, 'right' => $right]);
                        break;
                    case ExpressionParser::ARITY_BINARY:
                        $label = $lable ?? 'expression<binary>';
                        assert(null !== ($right = array_pop($output)));
                        assert(null !== ($left = array_pop($output)));
                        $output[] = new Ast($label, ['left' => $left, 'operator' => $member, 'right' => $right]);
                        break;
                    case ExpressionParser::ARITY_UNARY:
                        switch ($member->meta()->get('associativity')) {
                            case ExpressionParser::ASSOC_LEFT:
                                assert(null !== ($left = array_pop($output)));
                                $output[] = new Ast('expression<unary>', ['left' => $left, 'operator' => $member]);
                                break;
                            case ExpressionParser::ASSOC_RIGHT:
                            case ExpressionParser::ASSOC_NONE:
                                assert(null !== ($right = array_pop($output)));
                                $output[] = new Ast('expression<unary>', ['operator' => $member, 'right' => $right]);
                                break;
                        }
                        break;
                    default:
                        $output[] = $member;
                }

                if (count($output) === 1) return $output[0];

                return $this->error($ts);
            }

            function expected() : Expected {
                return $this->stack[0]->expected()->append($this->stack[1]->expected());
            }

            function isFallible() : bool {
                return $this->stack[0]->isFallible();
            }
        })
        ->as('expression');

        $this->id = count($this->parameters, COUNT_RECURSIVE);

        return $expression;
    }

    function addOperator(int $flags, ...$operators)
    {
        $this->validateBitTable($arity = $flags & self::ARITY, 'Operator can not have more than one arity at the same time.');
        $this->validateBitTable($associativity = $flags & self::ASSOC, 'Operator can not have more than one associativity at the same time.');
        $position = $this->inferPositionalBehavior($flags);
        $precedence = $this->inferPrecedence();

        foreach ($operators as $operator) {
            $operator = $operator instanceof Parser ? $operator : token($operator);
            $this->parameters['operators'][$position][] =
                new class('operator', $operator, $precedence, $arity, $associativity) extends Parser {
                    protected function parser(TokenStream $ts, Parser $operator, int $precedence, int $arity, int $associativity) /*: Result|null*/ {
                        if(($result = $operator->parse($ts)) instanceof Ast)
                            $result->as($this->label)->withMeta(Map::fromKeysAndValues(compact('precedence', 'arity', 'associativity')));

                        return $result;
                    }

                    function expected() : Expected {
                        return $this->stack[0]->expected();
                    }

                    function isFallible() : bool {
                        return $this->stack[0]->isFallible();
                    }
                };
        }
    }

    private function inferPositionalBehavior(int $flags) : int
    {
        foreach ([self::POS_PREFIX, self::POS_INFIX, self::POS_SUFFIX] as $p) if(($flags & $p) === $flags) return $p;

        throw new \Exception('Could not infer operator positional behaviour.');
    }

    /**
     * Checks if at least one and only one single bit is active or fails
     */
    private function validateBitTable(int $flags, string $message)
    {
        if (! ($flags && !($flags & ($flags-1)))) throw new \Exception($message);
    }

    private function inferPrecedence() : int
    {
        return $this->precedence += 10;
    }
}
