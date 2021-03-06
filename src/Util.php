<?php

namespace SmartUI;

use Closure;
use ReflectionException;
use ReflectionFunction;

class Util
{
    /**
     * @param $array
     * @return bool
     */
    public static function is_assoc($array)
    {
        foreach (array_keys($array) as $k => $v) {
            if ($k !== $v)
                return true;
        }
        return false;
    }

    /**
     * @param $str_value
     * @param bool $nl2br
     * @return string
     */
    public static function clean_html_string($str_value, $nl2br = true)
    {
        if (is_null($str_value)) $str_value = "";
        $new_str = is_string($str_value) ? htmlentities(html_entity_decode($str_value, ENT_QUOTES)) : $str_value;
        $new_str = utf8_encode($new_str);
        return $nl2br ? nl2br($new_str) : $new_str;
    }

    /**
     * @param bool $md5
     * @return string|string[]
     */
    public static function create_id($md5 = false)
    {
        $uid = uniqid((double)microtime() * 10000, true);
        $result = str_replace(".", "", $uid);
        $result = $md5 ? md5($result) : $result;
        return $result;
    }

    /**
     * @param $default_prop
     * @param $value
     * @param array $closure_defaults
     * @param string $default_key
     * @return array
     */
    public static function get_clean_structure($default_prop, $value, $closure_defaults = [], $default_key = '')
    {
        return self::get_property_value($value, array(
            'if_array' => function ($value) use ($default_prop, $default_key) {
                return self::set_array_prop_def($default_prop, $value, $default_key);
            },
            'if_closure' => function ($value) use ($closure_defaults, $default_prop, $default_key) {
                return self::set_closure_prop_def($default_prop, $value, $closure_defaults, $default_key);
            },
            'if_other' => function ($value) use ($default_prop, $default_key) {
                $default_prop[$default_key] = $value;
                return $default_prop;
            }
        ));
    }

    /**
     * @param $prop
     * @param $prop_methods
     * @return array
     */
    public static function get_property_value($prop, $prop_methods)
    {
        if (self::is_closure($prop)) {
            return isset($prop_methods['if_closure']) ? $prop_methods['if_closure']($prop) : $prop($prop);
        } else if (is_array($prop) || is_object($prop)) {
            if (is_object($prop))
                $prop = self::object_to_array($prop);
            return isset($prop_methods['if_array']) ? $prop_methods['if_array']($prop) : $prop;
        } else {
            return isset($prop_methods['if_other']) ? $prop_methods['if_other']($prop) : $prop;
        }
    }

    /**
     * @param $obj
     * @return bool
     */
    public static function is_closure($obj)
    {
        return (is_object($obj) && ($obj instanceof Closure));
    }

    /**
     * @param $object
     * @return array
     */
    public static function object_to_array($object)
    {
        if (!is_object($object) && !is_array($object))
            return $object;

        if (is_object($object))
            $object = get_object_vars((object)$object);

        return array_map(array(__CLASS__, 'object_to_array'), $object);
    }

    /**
     * @param $default_structure
     * @param $array_value
     * @param string $set_to_key_if_fail
     * @return mixed
     */
    public static function set_array_prop_def($default_structure, $array_value, $set_to_key_if_fail = "")
    {
        if ($set_to_key_if_fail != "") {
            if (!is_array($array_value)) {
                if (isset($default_structure[$set_to_key_if_fail]))
                    $default_structure[$set_to_key_if_fail] = $array_value;
                return $default_structure;
            }
        }

        foreach ($array_value as $key => $value) {
            $default_structure[$key] = $value;
        }
        return $default_structure;
    }

    /**
     * @param $default_structure
     * @param $callback_value
     * @param array $callback_defaults
     * @param string $set_to_key_if_fail
     * @return mixed
     * @throws ReflectionException
     */
    public static function set_closure_prop_def($default_structure, $callback_value, $callback_defaults = [], $set_to_key_if_fail = "")
    {
        if ($set_to_key_if_fail != "") {
            if (!self::is_closure($callback_value)) {
                if (isset($default_structure[$set_to_key_if_fail]))
                    $default_structure[$set_to_key_if_fail] = $callback_value;
                return $default_structure;
            }
        }

        $callback_return = self::run_callback($callback_value, $callback_defaults);

        if (is_array($callback_return)) {
            $default_structure = self::set_array_prop_def($default_structure, $callback_return);
        } else if ($set_to_key_if_fail != "" && isset($default_structure[$set_to_key_if_fail])) {
            $default_structure[$set_to_key_if_fail] = $callback_return;
        }
        return $default_structure;
    }

    /**
     * @param $callback
     * @param $default_args
     * @return mixed
     * @throws ReflectionException
     */
    public static function run_callback($callback, $default_args)
    {
        $reflection = new ReflectionFunction($callback);
        $params = $reflection->getParameters();
        if (!$params || !$default_args) return call_user_func($callback);

        $ref_args = array_keys($params);
        foreach ($ref_args as $param_index) {
            if (isset($default_args[$param_index]))
                $ref_args[$param_index] = $default_args[$param_index];
            else
                $ref_args[$param_index] = null;
        }

        return call_user_func_array($callback, $ref_args);
    }

    public static function replace_col_codes($str, $row, $url_encode = false)
    {
        preg_match_all("/\{([^&={{}}]+)\}/", $str, $matched_cols);
        $col_replace = [];
        $col_search = [];
        foreach ($matched_cols[1] as $matched_col) {
            if (is_array($row)) $row = self::array_to_object($row);
            if (isset($row->{$matched_col})) {
                $col_replace[] = $url_encode ? urlencode($row->{$matched_col}) : $row->{$matched_col};
                $col_search[] = "/{{" . $matched_col . "}}/";
            }
        }
        return preg_replace($col_search, $col_replace, $str);
    }

    /**
     * @param $array
     * @param bool $recursive
     * @return array|object
     */
    public static function array_to_object($array, $recursive = false)
    {
        if (!is_object($array) && !is_array($array))
            return $array;

        if (!$recursive) return (object)$array;

        if (is_array($array))
            return (object)array_map(array(__CLASS__, 'array_to_object'), $array);
        else return $array;
    }
}