<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

/**
 * Worst class ever. This needs to be replaced by a SyntaxObject or sort of
 */
class Ast implements Result {

    protected
        $label = null,
        $ast = []
    ;

    private
        $parent
    ;

    function __construct(string $label = null, $ast = []) {
        if ($ast instanceof self)
            throw new InvalidArgumentException('Unmerged AST.');

        $this->ast = $ast;
        $this->label = $label;
    }

    function __get($path)
    {
        $ret =
            \igorw\get_in(
                (null !== $this->label ? $this->all() : $this->ast),
                preg_split('/\s+/', $path)
            )
        ;

        if (null === $ret && $this->parent) $ret = $this->parent->{$path};

        return $ret;
    }

    function raw() {
        return $this->ast;
    }

    function token() : Token {
        return $this->ast;
    }

    function array() : array {
        return $this->ast;
    }

    function all() {
        return [($this->label ?? 0) => $this->ast];
    }

    function append(self $ast) : self {
        if (null !== $ast->label) {
            if (isset($this->ast[$ast->label]))
                throw new InvalidArgumentException(
                    "Duplicated AST label '{$ast->label}'.");

            $this->ast[$ast->label] = $ast->ast;
        }
        else $this->ast[] = $ast->ast;

        return $this;
    }

    function push(self $ast) : self {
        $this->ast[] = $ast->label ? [$ast->label => $ast->ast] : $ast->ast;

        return $this;
    }

    function isEmpty() : bool {
        return ! count($this->ast);
    }

    function as(/*string|null*/ $label = null) : self {
        if (null !== $label && null === $this->label) $this->label = $label;

        return $this;
    }

    function withParent(self $parent) : self {
        $this->parent = $parent;

        return $this;
    }
}
