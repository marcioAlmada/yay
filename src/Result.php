<?php declare(strict_types=1);

namespace Yay;

interface Result {
	function as(string $label = '') : self;
	function withMeta(Map $meta) : self;
	function meta() : Map;
}
