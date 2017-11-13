<?php namespace Yay;

include __DIR__ .'/vendor/autoload.php';

$testLine = (int) getenv('TEST_LINE');
$stopOnFailure = (bool) getenv('TEST_STOP');
$tracing = (bool) getenv('TEST_TRACE');
$tracing_usleep = (int) getenv('TEST_TRACE_USLEEP');

$fixtures = include __DIR__ . '/fixtures.php';

$start = new \DateTime;

if ($tracing) Parser::setTracer(new \Yay\ParserTracer\CliParserTracer);

foreach ($fixtures as $type => $sources) foreach($sources as $line => $source)  try {

    // if ('eval' === $type) continue;
    // if ('bad' === $type) continue;

    if ($testLine && $line != $testLine) continue;

    $ts = TokenStream::fromSourceWithoutOpenTag($source);
    $result = expression()->parse($ts);

    $token = $ts->index()->token ?: new Token(Token::NONE);

    $success = $ts->index() instanceof NodeEnd;

    switch ($type) {
        case 'eval':
            if ($success) {
                echo "[x] Passed good fixture test {$line} \033[1;30m{$ts->debug()}\033[0m", PHP_EOL;
                
                try {
                    $evaluated = (ev(printast($result)) === ev($source));
                }
                catch(\ParseError $e) {
                    $success = false;
                }
                // ignore certain errros thah might be caused by random inputs
                catch(\Throwable $e) {
                    $success = true;
                }
                
                if ($success)
                    echo "    Success evaluating Ast ", "\033[1;32m", '(', printast($result), ') === (', $source, ') === ', $evaluated, PHP_EOL, "\033[0m";
                else {
                    file_put_contents(__DIR__ . '/random_fixtures.php', '__LINE__ => ' . "'{$source}'," . PHP_EOL, FILE_APPEND| LOCK_EX);
                    echo "    Failed evaluating Ast ", "\033[1;31m", '(', printast($result), ') === (', $source, ') === ', $evaluated, PHP_EOL, "\033[0m";   
                }
            }
            else
                echo "[ ]\033[1;31m Failed good fixture test {$line} at token {$token->dump()}\033[0m\n\n    {$ts->debug()}", PHP_EOL, PHP_EOL;
        break;
        case 'good':
            if ($success)
                echo "[x] Passed good fixture test {$line} \033[1;30m{$ts->debug()}\033[0m", PHP_EOL;
            else
                echo "[ ]\033[1;31m Failed good fixture test {$line} at token {$token->dump()}\033[0m\n\n    {$ts->debug()}", PHP_EOL, PHP_EOL;
        break;
        case 'bad':
            if ($success)
                echo "[ ]\033[1;31m Failed evil fixture test {$line} at token {$token->dump()}\033[0m\n\n    {$ts->debug()}", PHP_EOL, PHP_EOL;
            else
                echo "[x] Passed evil fixture test {$line} \033[1;30m{$ts->debug()}\033[0m", PHP_EOL;
        break;
    }

    if (false === $success && $stopOnFailure) break 2;
}
catch(\Throwable $e) {
    $token = $ts->index()->token ?: new Token(Token::NONE);
    echo "[ ]\033[1;31m Failed test {$line} {$source}\033[1;30m Exception(`{$e->getMessage()}`)\033[0m", PHP_EOL, PHP_EOL;
    echo $e, PHP_EOL, PHP_EOL;
    if ($stopOnFailure) break;
}

$end = new \DateTime;

echo PHP_EOL, 'Test took ', $start->diff($end)->format('%h:%i:%s.%a.%F'), PHP_EOL;
echo 'Test used ' . (memory_get_peak_usage(true)/1024/1024) . ' MiB of memory', PHP_EOL;

function printast(Ast $ast){
    $buff = '';

    if ($ast->operator) {
        switch ($ast->operator->meta()->get('arity')) {
            case ExpressionParser::ARITY_BINARY:
                $buff .= '(';
                $buff .= printast($ast->left);
                $buff .= ' ' . printast($ast->operator) . ' ';
                $buff.= printast($ast->right);
                $buff .= ')';
                break;
            case ExpressionParser::ARITY_TERNARY:
                $buff .= '(';
                $buff .= printast($ast->left);
                $buff .= ' ' . printast($ast->middle) . ' ';
                $buff.= printast($ast->right);
                $buff .= ')';
                break;
            case ExpressionParser::ARITY_UNARY:
                switch ($ast->operator->meta()->get('associativity')) {
                    case ExpressionParser::ASSOC_NONE:
                    case ExpressionParser::ASSOC_LEFT:
                        $buff .= printast($ast->left);
                        $buff .= ' ' . printast($ast->operator) . ' ';
                        break;
                    case ExpressionParser::ASSOC_RIGHT:
                        $buff .= ' ' . printast($ast->operator) . ' ';
                        $buff.= printast($ast->right);
                        break;
                    default:
                        throw new \Exception('Unknown operator associativity.');
                }
                break;
            default:
                throw new \Exception('Unknown operator arity.');
        }
    }
    else $buff .= implode('', $ast->tokens());

    return $buff;
}

function ev($src)
{
    return eval('return '. $src . ';');
}

function stringify($expr) : string
{
    return json_encode(($expr));
}
