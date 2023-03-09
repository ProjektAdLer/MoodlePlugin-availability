<?php

namespace availability_adler;


use coding_exception;
use core_availability\condition as availability_condition;
use core_availability\info;
use core_plugin_manager;
use invalid_parameter_exception;

class condition extends availability_condition {
    protected string $condition;

    /**
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public function __construct($structure) {
        if (isset($structure->condition)) {
            try {
                $this->evaluate_room_requirements($structure->condition, 0, true);
            } catch (invalid_parameter_exception $e) {
                throw new invalid_parameter_exception('Invalid condition: ' . $e->getMessage());
            }
            $this->condition = $structure->condition;
        } else {
            throw new coding_exception('adler statement not set');
        }
    }

    /**
     * @param $statement
     * @param $userid
     * @param $validation_mode bool If set to true, this method is used to validate the condition. In this case,
     * the method will not call external methods. All calls to evaluate_room will be replaced with a "true" value.
     * @return bool
     * @throws invalid_parameter_exception
     */
    protected function evaluate_room_requirements($statement, $userid, bool $validation_mode = false): bool {
        // search for brackets
        for ($i = 0; $i < strlen($statement); $i++) {
            if ($statement[$i] == '(') {
                $start = $i;
                $end = $i;
                $depth = 1;
                for ($j = $i + 1; $j < strlen($statement); $j++) {
                    if ($statement[$j] == '(') {
                        $depth++;
                    } else if ($statement[$j] == ')') {
                        $depth--;
                    }
                    if ($depth == 0) {
                        $end = $j;
                        break;
                    }
                }
                $substatement = substr($statement, $start + 1, $end - $start - 1);
                $result = $this->evaluate_room_requirements($substatement, $userid)? 't' : 'f';
                $statement = substr($statement, 0, $start) . $result . substr($statement, $end + 1);
                $i = $start;
            }
        }

        // Search for AND and OR following the rule "AND before OR"
        // search for AND (^)
        for ($i = 0; $i < strlen($statement); $i++) {
            if ($statement[$i] == '^') {
                $left = substr($statement, 0, $i);
                $right = substr($statement, $i + 1);
                $statement = ($this->evaluate_room_requirements($left, $userid) == 't' && $this->evaluate_room_requirements($right, $userid) == 't')? 't' : 'f';
                break;
            }
        }
        // search for OR (v)
        for ($i = 0; $i < strlen($statement); $i++) {
            if ($statement[$i] == 'v') {
                $left = substr($statement, 0, $i);
                $right = substr($statement, $i + 1);
                $statement = ($this->evaluate_room_requirements($left, $userid) == 't'|| $this->evaluate_room_requirements($right, $userid)=='t')?'t':'f';
                break;
            }
        }

        // search for NOT (!)
        for ($i = 0; $i < strlen($statement); $i++) {
            if ($statement[$i] == '!') {
                $right = substr($statement, $i + 1);
                $statement = (!$this->evaluate_room_requirements($right, $userid)=='t')?'t':'f';
                break;
            }
        }

        // If this place is reached the statement should be only a number (room id)
        if (is_numeric($statement)) {
            $statement = $validation_mode || $this->evaluate_room((int)$statement, $userid);
        } else if ($statement == 't' || $statement == 'f') {
            $statement = $statement == 't';
        } else {
            throw new invalid_parameter_exception('Invalid statement: ' . $statement);
        }

        return $statement;
    }

    protected function evaluate_room($roomid, $userid): bool {
        // TODO: implement (requires local_adler functionality not yet existing)
        return false;
    }

    public function is_available($not, info $info, $grabthelot, $userid) {
        // check if local_adler is available
        $plugins = core_plugin_manager::instance()->get_installed_plugins('local');
        if (!array_key_exists('adler', $plugins)) {
            debugging('local_adler is not available', E_WARNING);
            $allow = true;
        } else {
            $allow = $this->evaluate_room_requirements($this->condition, $userid);
        }

        if ($not) {
            $allow = !$allow;
        }

        return $allow;
    }

    public function get_description($full, $not, info $info) {
        return get_string('description_previous_rooms_required', 'availability_adler', $this->condition);
    }

    protected function get_debug_string() {
        return 'Room condition: ' . $this->condition;
    }

    public function save() {
        return (object) [
            'type' => 'adler',
            'condition' => $this->condition,
        ];
    }

    // TODO: include_after_restore/... ???
}