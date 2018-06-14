<?php

namespace ZxcvbnPhp\Matchers;

class SequenceMatch extends Match
{
    const MAX_DELTA = 5;

    /**
     * @var
     */
    public $sequenceName;

    /**
     * @var
     */
    public $sequenceSpace;

    /**
     * @var
     */
    public $ascending;

    /**
     * Match sequences of three or more characters.
     *
     * @copydoc Match::match()
     */
    public static function match($password, array $userInputs = array())
    {
        $matches = array();
        $passwordLength = strlen($password);

        if ($passwordLength === 1) {
            return array();
        }

        $begin = 0;
        $lastDelta = null;

        for ($index = 1; $index < $passwordLength; $index++) {
            $delta = ord($password[$index]) - ord($password[$index - 1]);
            if ($lastDelta === null) {
                $lastDelta = $delta;
            }
            if ($lastDelta === $delta) {
                continue;
            }

            static::findSequenceMatch($password, $begin, $index - 1, $lastDelta, $matches);
            $begin = $index - 1;
            $lastDelta = $delta;
        }

        static::findSequenceMatch($password, $begin, $passwordLength - 1, $lastDelta, $matches);

        return $matches;
    }

    public static function findSequenceMatch($password, $begin, $end, $delta, &$matches)
    {
        if ($end - $begin > 1 || abs($delta) === 1) {
            if (abs($delta) > 0 && abs($delta) <= self::MAX_DELTA) {
                $token = substr($password, $begin, $end - $begin + 1);
                if (preg_match('/^[a-z]+$/', $token)) {
                    $sequenceName = 'lower';
                    $sequenceSpace = 26;
                } elseif (preg_match('/^[A-Z]+$/', $token)) {
                    $sequenceName = 'upper';
                    $sequenceSpace = 26;
                } elseif (preg_match('/^\d+$/', $token)) {
                    $sequenceName = 'digits';
                    $sequenceSpace = 10;
                } else {
                    $sequenceName = 'unicode';
                    $sequenceSpace = 26;
                }

                $matches[] = new static($password, $begin, $end, $token, [
                    'sequenceName' => $sequenceName,
                    'sequenceSpace' => $sequenceSpace,
                    'ascending' => $delta > 0,
                ]);
                return;
            }
        }
    }

    public function getFeedback($isSoleMatch)
    {
        return array(
            'warning' => "Sequences like abc or 6543 are easy to guess",
            'suggestions' => array(
                'Avoid sequences'
            )
        );
    }

    /**
     * @param $password
     * @param $begin
     * @param $end
     * @param $token
     * @param array $params
     */
    public function __construct($password, $begin, $end, $token, $params = array())
    {
        parent::__construct($password, $begin, $end, $token);
        $this->pattern = 'sequence';
        if (!empty($params)) {
            $this->sequenceName = isset($params['sequenceName']) ? $params['sequenceName'] : null;
            $this->sequenceSpace = isset($params['sequenceSpace']) ? $params['sequenceSpace'] : null;
            $this->ascending = isset($params['ascending']) ? $params['ascending'] : null;
        }
    }

    /**
     * @copydoc Match::getEntropy()
     */
    public function getEntropy()
    {
        $char = $this->token[0];
        if ($char === 'a' || $char === '1') {
            $entropy = 1;
        }
        else {
            $ord = ord($char);

            if ($this->isDigit($ord)) {
                $entropy = $this->log(10);
            }
            elseif ($this->isLower($ord)) {
                $entropy = $this->log(26);
            }
            else {
                $entropy = $this->log(26) + 1; // Extra bit for upper.
            }
        }

        if (empty($this->ascending)) {
            $entropy += 1; // Extra bit for descending instead of ascending
        }

        return $entropy + $this->log(strlen($this->token));
    }
}
