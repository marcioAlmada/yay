<?php declare(strict_types=1);

namespace Yay;

class GrammarPattern extends Pattern implements PatternInterface {

    const
        E_GRAMMAR_UNREACHABLE_NONTERMINAL = "Macro grammar '%s {...}' has unreacheable nonterminal rules on line %d: %s",
        E_GRAMMAR_NON_FALLIBLE = 'Grammar %s forms a non fallible pattern on line %d',
        E_GRAMMAR_MULTIPLE_PRODUCTIONS = 'Grammar has more than one production rule on line %d. Rules are: %s',
        E_GRAMMAR_MISSING_PRODUCTION = "Grammar misses a production rule with modifier '<<' at macro on line %d",
        E_GRAMMAR_DUPLICATED_RULE_LABEL = "Grammar has duplicated rule labels %s on line %d"
    ;

    protected
        $scope,
        $unreached,
        $staged,
        $collected,
        $references = [],
        $pattern,
        $specificity
    ;

    function __construct(int $line, array $pattern, Map $tags, Map $scope) {
        if (0 === \count($pattern))
            $this->fail(self::E_EMPTY_PATTERN, $line);

        $this->scope = $scope;
        $this->unreached = new Map;
        $this->staged = new Map;
        $this->collected = new Map;
        $this->pattern = $this->compile($line, $pattern);
    }

    private function compile(int $line, array $tokens) {

        $label = rtoken('/^·\w+$/')->as('label');

        $doubleQuotes = token(T_CONSTANT_ENCAPSED_STRING, "''");

        $commit = chain(token('!'), token('!'))->as('commit');

        $literal = between($doubleQuotes, any(), $doubleQuotes)->as('literal');

        $constant = rtoken('/^T_\w+$/')->as('constant');

        $optionalModifier = optional(token('?'), false)->as('optional?');

        $productionModifier = optional(token(T_SL), false)->as('production?');

        $parser = (
            chain
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
                            label()->as('label')
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
            ->as('parser')
        );

        $labelReference =
            chain
            (
                $label
                ,
                optional
                (
                    chain
                    (
                        token('{')
                        ,
                        token('}')
                        ,
                        $label
                    )
                    ,
                    null
                )
                ->as('alias')
            )
            ->as('reference')
        ;

        $list =
            chain
            (
                $optionalModifier
                ,
                token(T_LIST)
                ,
                token('(')
                ,
                pointer($sequence)->as('member')
                ,
                token(',')
                ,
                (clone $literal)->as('delimiter')
                ,
                token(')')
            )
            ->as('list')
        ;

        $sequence =
            repeat
            (
                either
                (
                    $list
                    ,
                    $parser
                    ,
                    $labelReference
                    ,
                    $constant
                    ,
                    $literal
                    ,
                    $commit
                )
            )
            ->as('sequence')
        ;

        $disjunction = ls($sequence, token('|'))->as('disjunction');

        $rule =
            commit
            (
                chain
                (
                    $productionModifier
                    ,
                    $label
                    ,
                    $optionalModifier
                    ,
                    either
                    (
                        between
                        (
                            token('{')
                            ,
                            $sequence
                            ,
                            token('}')
                        )
                        ,
                        between
                        (
                            token('{')
                            ,
                            $disjunction
                            ,
                            token('}')
                        )
                    )
                )
            )
            ->as('rule')
        ;

        $grammar =
            commit
            (
                chain
                (
                    optional
                    (
                        repeat($rule)
                    )
                    ->as('rules')
                )
            )
        ;

        $grammarAst = $grammar->parse(TokenStream::fromSlice($tokens));

        $productions = new Map;

        foreach ($grammarAst->{'* rules'}->list() as $ast) {

            $ruleAst = $ast->{'* rule'};
            $labelAst = $ast->{'* rule label'};
            $label = (string) $labelAst->token();

            if ($ruleAst->{'production?'}) {

                $productions->add($label, $ruleAst);

                if ($productions->count() > 1) {
                    $this->fail(
                        self::E_GRAMMAR_MULTIPLE_PRODUCTIONS,
                        $line,
                        json_encode($productions->symbols(), self::PRETTY_PRINT)
                    );
                }

                continue;
            }

            if ($this->unreached->contains($label))
                $this->fail(self::E_GRAMMAR_DUPLICATED_RULE_LABEL, $label, $labelAst->token()->line());

            $this->unreached->add($label, $ruleAst);
        }

        if ($productions->count() === 0)
            $this->fail(self::E_GRAMMAR_MISSING_PRODUCTION, $line);

        $productionLabel = $productions->symbols()[0];

        $this->scope->add($productionLabel);

        $pattern = $this->compilePattern($productions->get($productionLabel));

        if ($this->unreached->count() > 0) {
            $this->fail(
                self::E_GRAMMAR_UNREACHABLE_NONTERMINAL,
                $productionLabel,
                $line,
                json_encode($this->unreached->symbols(), self::PRETTY_PRINT)
            );
        }

        if ($this->staged->count() > 0) {
            $this->fail(
                self::E_GRAMMAR_UNREACHABLE_NONTERMINAL,
                $productionLabel,
                $line,
                json_encode($this->staged->symbols(), self::PRETTY_PRINT)
            );
        }

        $this->specificity = $this->collected->count();

        if (! $pattern->isFallible())
           $this->fail(self::E_GRAMMAR_NON_FALLIBLE, $productionLabel, $line);

        return $pattern;
    }

    private function compilePattern(Ast $rule) : Parser {

        $label = (string) $rule->{'label'};

        if (! ($sequence = $rule->{'* sequence'})->isEmpty())
            $pattern = $this->compileSequence($sequence, $label);
        else if(! ($disjunction = $rule->{'* disjunction'})->isEmpty())
            $pattern = $this->compileDisjunction($disjunction, $label);
        else
            assert(false, 'Unknown pattern definition.');

        if ($rule->{'optional?'}) $pattern = optional($pattern);

        $pattern->as($label);

        return $pattern;
    }

    private function compileSequence(Ast $sequence, string $label) : Parser {
        $commit = false;
        $this->staged->add($label);
        $chain = [];
        foreach ($sequence->list() as $step) {
            foreach ($step->list() as $ast) {
                $type = $ast->label();
                switch ($type) {
                    case 'literal': // matches double quoted like: '','' or ''use''
                        $chain[] = token($ast->token());
                        break;
                    case 'constant': // T_*
                        $chain[] = token(parent::lookupTokenType($ast->token()));
                        break;
                    case 'parser':
                        $chain[] = parent::compileParser($ast);
                        break;
                    case 'reference':
                        $refLabel = (string) $ast->{'label'};
                        $link = $this->collected->get($refLabel);
                        if ($link === null) {
                            if ($this->staged->contains($refLabel)) {
                                $link = pointer($this->references[$refLabel]);
                            }
                            else {
                                $link = $this->compilePattern($this->unreached->get($refLabel));
                                $this->references[$refLabel] = $link;
                                $this->collected->add($refLabel, $link);
                                $this->unreached->remove($refLabel);
                            }
                        }

                        $link = (clone $link)->as((string) $ast->{'alias label'} ?: null);

                        $chain[] = $link;
                        break;
                    case 'list':
                        $link = $this->compileSequence($ast->{'* member'}, $label);
                        $chain[] = optional(ls($link, token($ast->{'* delimiter'}->token())));
                        break;
                    case 'commit':
                        $commit = true;
                        break;
                    default:
                        assert(false, 'Unknown sequence step.');
                        break;
                }

                if ($commit && ($length = count($chain)) > 0) $chain[$length-1] = commit(end($chain));
            }
        }

        if (count($chain) > 1) {
            $pattern = chain(...$chain);
            $pattern->as($label);
        }
        else {
            $pattern = $chain[0];
        }

        $this->staged->remove($label);

        return $pattern;
    }

    private function compileDisjunction(Ast $disjunctions, string $label) : Parser {

        $this->staged->add($label);

        $chain = [];
        foreach ($disjunctions->list() as $disjunction) {
            foreach ($disjunction->list() as $sequence) {
                $link = $this->compileSequence($sequence, $label);
                $this->collected->add($label, $link);
                $this->unreached->remove($label);
                $chain[] = $link;
            }
        }

        $pattern = either(...$chain)->as($label);

        $this->staged->remove($label);

        return $pattern;
    }
}
