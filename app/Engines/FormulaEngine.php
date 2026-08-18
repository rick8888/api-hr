<?php

declare(strict_types=1);

namespace App\Engines;

use App\Engines\Exceptions\FormulaException;

/**
 * FormulaEngine
 *
 * Safe arithmetic & logical expression evaluator. NO eval(), NO call_user_func.
 *
 * Supported syntax:
 *   - Numbers (int, float)
 *   - Variables: BASE_SALARY, GROSS, TAXABLE, NET, or any provided context key
 *   - Arithmetic: +  -  *  /  %  ^   (^ = power)
 *   - Comparison: <  <=  >  >=  ==  !=
 *   - Logical:    && || !
 *   - Parentheses: ( )
 *   - Functions: MIN(a,b,...), MAX(a,b,...), IF(cond,a,b),
 *                ROUND(x), ROUND(x,n), CEIL(x), FLOOR(x), ABS(x), SUM(a,b,...)
 *
 * Implementation: tokenizer + Shunting-Yard (RPN) + stack-based RPN evaluator.
 *
 * No reflection, no eval, no closures from external sources.
 */
final class FormulaEngine
{
    private const T_NUMBER = 'NUMBER';
    private const T_IDENT = 'IDENT';
    private const T_OP = 'OP';
    private const T_LPAREN = 'LPAREN';
    private const T_RPAREN = 'RPAREN';
    private const T_COMMA = 'COMMA';
    private const T_FUNC = 'FUNC';

    /** @var array<string, array{prec:int, assoc:string, args:int}> */
    private const OPERATORS = [
        '!u' => ['prec' => 9, 'assoc' => 'right', 'args' => 1],
        'u-' => ['prec' => 9, 'assoc' => 'right', 'args' => 1],
        'u+' => ['prec' => 9, 'assoc' => 'right', 'args' => 1],
        '^'  => ['prec' => 8, 'assoc' => 'right', 'args' => 2],
        '*'  => ['prec' => 7, 'assoc' => 'left',  'args' => 2],
        '/'  => ['prec' => 7, 'assoc' => 'left',  'args' => 2],
        '%'  => ['prec' => 7, 'assoc' => 'left',  'args' => 2],
        '+'  => ['prec' => 6, 'assoc' => 'left',  'args' => 2],
        '-'  => ['prec' => 6, 'assoc' => 'left',  'args' => 2],
        '<'  => ['prec' => 5, 'assoc' => 'left',  'args' => 2],
        '<=' => ['prec' => 5, 'assoc' => 'left',  'args' => 2],
        '>'  => ['prec' => 5, 'assoc' => 'left',  'args' => 2],
        '>=' => ['prec' => 5, 'assoc' => 'left',  'args' => 2],
        '==' => ['prec' => 4, 'assoc' => 'left',  'args' => 2],
        '!=' => ['prec' => 4, 'assoc' => 'left',  'args' => 2],
        '&&' => ['prec' => 3, 'assoc' => 'left',  'args' => 2],
        '||' => ['prec' => 2, 'assoc' => 'left',  'args' => 2],
    ];

    /** @var array<string, int> Function name => arity (-1 = variadic) */
    private const FUNCTIONS = [
        'MIN'   => -1,
        'MAX'   => -1,
        'SUM'   => -1,
        'IF'    => 3,
        'ROUND' => -1, // 1 or 2
        'CEIL'  => 1,
        'FLOOR' => 1,
        'ABS'   => 1,
    ];

    /**
     * Evaluate a formula string with a variables context.
     *
     * @param array<string, int|float|bool|null> $context
     */
    public function evaluate(string $expression, array $context = []): float
    {
        $expression = trim($expression);
        if ($expression === '') {
            return 0.0;
        }

        $tokens = $this->tokenize($expression);
        $rpn = $this->shuntingYard($tokens);

        return (float) $this->evaluateRpn($rpn, $this->normalizeContext($context));
    }

    public function isValid(string $expression): bool
    {
        try {
            $this->shuntingYard($this->tokenize($expression));
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, int|float|bool|null> $context
     * @return array<string, float>
     */
    private function normalizeContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $out[(string) $k] = is_bool($v) ? ($v ? 1.0 : 0.0) : (float) ($v ?? 0);
        }
        return $out;
    }

    /**
     * @return list<array{type:string, value:string}>
     */
    private function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;
        $prevType = null;

        while ($i < $len) {
            $c = $expr[$i];

            if (ctype_space($c)) {
                $i++;
                continue;
            }

            // Number
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($expr[$i + 1]))) {
                $start = $i;
                $hasDot = $c === '.';
                $i++;
                while ($i < $len) {
                    $ch = $expr[$i];
                    if (ctype_digit($ch)) {
                        $i++;
                    } elseif ($ch === '.' && !$hasDot) {
                        $hasDot = true;
                        $i++;
                    } else {
                        break;
                    }
                }
                $tokens[] = ['type' => self::T_NUMBER, 'value' => substr($expr, $start, $i - $start)];
                $prevType = self::T_NUMBER;
                continue;
            }

            // Identifier / function
            if (ctype_alpha($c) || $c === '_') {
                $start = $i;
                while ($i < $len && (ctype_alnum($expr[$i]) || $expr[$i] === '_')) {
                    $i++;
                }
                $name = substr($expr, $start, $i - $start);

                // skip whitespace before potential '('
                $j = $i;
                while ($j < $len && ctype_space($expr[$j])) {
                    $j++;
                }
                if ($j < $len && $expr[$j] === '(' && isset(self::FUNCTIONS[strtoupper($name)])) {
                    $tokens[] = ['type' => self::T_FUNC, 'value' => strtoupper($name)];
                    $prevType = self::T_FUNC;
                    $i = $j;
                    continue;
                }

                // boolean literals
                $upper = strtoupper($name);
                if ($upper === 'TRUE' || $upper === 'FALSE') {
                    $tokens[] = ['type' => self::T_NUMBER, 'value' => $upper === 'TRUE' ? '1' : '0'];
                    $prevType = self::T_NUMBER;
                    continue;
                }

                $tokens[] = ['type' => self::T_IDENT, 'value' => $name];
                $prevType = self::T_IDENT;
                continue;
            }

            // 2-char operators
            $two = substr($expr, $i, 2);
            if (in_array($two, ['<=', '>=', '==', '!=', '&&', '||'], true)) {
                $tokens[] = ['type' => self::T_OP, 'value' => $two];
                $prevType = self::T_OP;
                $i += 2;
                continue;
            }

            // 1-char tokens
            switch ($c) {
                case '(':
                    $tokens[] = ['type' => self::T_LPAREN, 'value' => '('];
                    $prevType = self::T_LPAREN;
                    $i++;
                    continue 2;
                case ')':
                    $tokens[] = ['type' => self::T_RPAREN, 'value' => ')'];
                    $prevType = self::T_RPAREN;
                    $i++;
                    continue 2;
                case ',':
                    $tokens[] = ['type' => self::T_COMMA, 'value' => ','];
                    $prevType = self::T_COMMA;
                    $i++;
                    continue 2;
                case '+':
                case '-':
                    $isUnary = $prevType === null
                        || $prevType === self::T_OP
                        || $prevType === self::T_LPAREN
                        || $prevType === self::T_COMMA
                        || $prevType === self::T_FUNC;
                    $tokens[] = [
                        'type' => self::T_OP,
                        'value' => $isUnary ? ($c === '-' ? 'u-' : 'u+') : $c,
                    ];
                    $prevType = self::T_OP;
                    $i++;
                    continue 2;
                case '!':
                    $tokens[] = ['type' => self::T_OP, 'value' => '!u'];
                    $prevType = self::T_OP;
                    $i++;
                    continue 2;
                case '*':
                case '/':
                case '%':
                case '^':
                case '<':
                case '>':
                    $tokens[] = ['type' => self::T_OP, 'value' => $c];
                    $prevType = self::T_OP;
                    $i++;
                    continue 2;
            }

            throw new FormulaException("Unexpected character '{$c}' at position {$i}");
        }

        return $tokens;
    }

    /**
     * @param list<array{type:string,value:string}> $tokens
     * @return list<array{type:string,value:string,argc?:int}>
     */
    private function shuntingYard(array $tokens): array
    {
        $output = [];
        $stack = [];
        /**
         * Each entry tracks one function call we're currently inside:
         *   ['argc' => int, 'has_arg' => bool]
         * argc = number of arguments fully accumulated.
         * has_arg = true if at least one value has been produced for the current arg slot.
         *
         * @var list<array{argc:int, has_arg:bool}> $argStack
         */
        $argStack = [];

        $markValueProduced = static function () use (&$argStack): void {
            if (empty($argStack)) {
                return;
            }
            $top = &$argStack[count($argStack) - 1];
            if (!$top['has_arg']) {
                $top['has_arg'] = true;
                $top['argc']++;
            }
        };

        foreach ($tokens as $token) {
            $type = $token['type'];
            $value = $token['value'];

            switch ($type) {
                case self::T_NUMBER:
                case self::T_IDENT:
                    $output[] = $token;
                    $markValueProduced();
                    break;

                case self::T_FUNC:
                    $stack[] = $token;
                    break;

                case self::T_COMMA:
                    while (!empty($stack) && end($stack)['type'] !== self::T_LPAREN) {
                        $output[] = array_pop($stack);
                    }
                    if (empty($stack)) {
                        throw new FormulaException('Misplaced comma or unmatched parenthesis');
                    }
                    if (empty($argStack)) {
                        throw new FormulaException('Comma outside of function call');
                    }
                    // The current arg is closed; reset has_arg for the next one.
                    $argStack[count($argStack) - 1]['has_arg'] = false;
                    break;

                case self::T_OP:
                    $o1 = self::OPERATORS[$value] ?? null;
                    if ($o1 === null) {
                        throw new FormulaException("Unknown operator '{$value}'");
                    }
                    while (!empty($stack)) {
                        $top = end($stack);
                        if ($top['type'] !== self::T_OP) {
                            break;
                        }
                        $o2 = self::OPERATORS[$top['value']];
                        if (
                            ($o1['assoc'] === 'left' && $o1['prec'] <= $o2['prec']) ||
                            ($o1['assoc'] === 'right' && $o1['prec'] < $o2['prec'])
                        ) {
                            $output[] = array_pop($stack);
                            continue;
                        }
                        break;
                    }
                    $stack[] = $token;
                    break;

                case self::T_LPAREN:
                    $stack[] = $token;
                    $prevStackTop = count($stack) >= 2 ? $stack[count($stack) - 2] : null;
                    if ($prevStackTop !== null && $prevStackTop['type'] === self::T_FUNC) {
                        $argStack[] = ['argc' => 0, 'has_arg' => false];
                    }
                    break;

                case self::T_RPAREN:
                    while (!empty($stack) && end($stack)['type'] !== self::T_LPAREN) {
                        $output[] = array_pop($stack);
                    }
                    if (empty($stack)) {
                        throw new FormulaException('Mismatched parentheses');
                    }
                    array_pop($stack); // discard '('

                    if (!empty($stack) && end($stack)['type'] === self::T_FUNC) {
                        $func = array_pop($stack);
                        if (empty($argStack)) {
                            throw new FormulaException('Function call lost arg-tracking state');
                        }
                        $frame = array_pop($argStack);
                        $func['argc'] = $frame['argc'];
                        $output[] = $func;
                    }

                    // The whole parenthesised expression (function or grouping)
                    // produced a single value: notify the surrounding function (if any).
                    $markValueProduced();
                    break;
            }
        }

        while (!empty($stack)) {
            $top = array_pop($stack);
            if ($top['type'] === self::T_LPAREN || $top['type'] === self::T_RPAREN) {
                throw new FormulaException('Mismatched parentheses');
            }
            $output[] = $top;
        }

        return $output;
    }

    /**
     * @param list<array{type:string,value:string,argc?:int}> $rpn
     * @param array<string, float> $context
     */
    private function evaluateRpn(array $rpn, array $context): float
    {
        /** @var list<float> $stack */
        $stack = [];

        foreach ($rpn as $token) {
            switch ($token['type']) {
                case self::T_NUMBER:
                    $stack[] = (float) $token['value'];
                    break;

                case self::T_IDENT:
                    $name = $token['value'];
                    if (!array_key_exists($name, $context)) {
                        // Tolerant: missing variables resolve to 0 to avoid breaking
                        // payrolls when an optional component is absent.
                        $stack[] = 0.0;
                    } else {
                        $stack[] = (float) $context[$name];
                    }
                    break;

                case self::T_OP:
                    $stack[] = $this->applyOperator($token['value'], $stack);
                    break;

                case self::T_FUNC:
                    $argc = $token['argc'] ?? 0;
                    $stack[] = $this->applyFunction($token['value'], $argc, $stack);
                    break;

                default:
                    throw new FormulaException("Unexpected token in RPN: {$token['type']}");
            }
        }

        if (count($stack) !== 1) {
            throw new FormulaException('Malformed expression');
        }

        return $stack[0];
    }

    /**
     * @param list<float> $stack
     */
    private function applyOperator(string $op, array &$stack): float
    {
        $arity = self::OPERATORS[$op]['args'];
        if (count($stack) < $arity) {
            throw new FormulaException("Operator '{$op}' missing operands");
        }

        if ($arity === 1) {
            $a = array_pop($stack);
            return match ($op) {
                'u-' => -$a,
                'u+' => +$a,
                '!u' => ($a == 0.0) ? 1.0 : 0.0,
                default => throw new FormulaException("Unknown unary op '{$op}'"),
            };
        }

        $b = array_pop($stack);
        $a = array_pop($stack);

        return match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $b == 0.0 ? 0.0 : $a / $b,
            '%' => $b == 0.0 ? 0.0 : fmod($a, $b),
            '^' => $a ** $b,
            '<' => $a < $b ? 1.0 : 0.0,
            '<=' => $a <= $b ? 1.0 : 0.0,
            '>' => $a > $b ? 1.0 : 0.0,
            '>=' => $a >= $b ? 1.0 : 0.0,
            '==' => $a == $b ? 1.0 : 0.0,
            '!=' => $a != $b ? 1.0 : 0.0,
            '&&' => ($a != 0.0 && $b != 0.0) ? 1.0 : 0.0,
            '||' => ($a != 0.0 || $b != 0.0) ? 1.0 : 0.0,
            default => throw new FormulaException("Unknown operator '{$op}'"),
        };
    }

    /**
     * @param list<float> $stack
     */
    private function applyFunction(string $name, int $argc, array &$stack): float
    {
        if (!isset(self::FUNCTIONS[$name])) {
            throw new FormulaException("Unknown function '{$name}'");
        }

        $expected = self::FUNCTIONS[$name];
        if ($expected !== -1 && $argc !== $expected) {
            throw new FormulaException("Function {$name} expects {$expected} args, got {$argc}");
        }

        if (count($stack) < $argc) {
            throw new FormulaException("Function {$name} missing arguments");
        }

        $args = [];
        for ($i = 0; $i < $argc; $i++) {
            array_unshift($args, array_pop($stack));
        }

        return match ($name) {
            'MIN' => $argc === 0 ? 0.0 : min($args),
            'MAX' => $argc === 0 ? 0.0 : max($args),
            'SUM' => array_sum($args),
            'IF' => ($args[0] != 0.0) ? $args[1] : $args[2],
            'ROUND' => $argc === 1 ? round($args[0]) : round($args[0], (int) $args[1]),
            'CEIL' => ceil($args[0]),
            'FLOOR' => floor($args[0]),
            'ABS' => abs($args[0]),
            default => throw new FormulaException("Unhandled function '{$name}'"),
        };
    }
}
