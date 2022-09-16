<?php

/**
 * Environment Variable
 *
 * Kevinralph Tenorio <d3@kevyworks.com>
 */
class Env
{
    /**
     * Use $_ENV
     */
    const MODE_ENV = 0x01;

    /**
     * Use $_SERVER
     */
    const MODE_SERVER = 0x02;

    /**
     * Use putenv/getenv
     */
    const MODE_PUTENV = 0x03;

    /**
     * Mode
     *
     * @var integer $mode
     */
    protected static $mode = self::MODE_ENV;

    /**
     * Set Default Mode
     *
     * @param $mode
     * @return void
     */
    public static function setMode($mode = null)
    {
        if (self::isValidMode($mode)) {
            static::$mode = $mode;
        }
    }

    /**
     * Load an environment file.
     *
     * @param array|string $filePaths Accepts multiple ENV file paths
     * @param boolean $override
     * @param mixed $mode
     * @return void
     */
    public static function loadEnvFile($filePaths, $override = false, $mode = null)
    {
        self::setMode(is_null($mode) ? self::MODE_ENV : $mode);
        
        $filePaths = ! is_array($filePaths) ? [$filePaths] : $filePaths;
        $default = microtime(1);
        static $parsed = [];

        foreach ($filePaths as $filePath) {
            $content = preg_replace('/#.+/m', '', file_get_contents($filePath));

            if (preg_match_all('/(^\S|[^=]|.+)=([^=]|.*)$/m', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    list(, $key, $value) = $match;

                    if (! $override && $default !== self::getEnv($key, $default)) {
                        // Skip if we already have value and cant override.
                        continue;
                    }

                    $parsed[$key] = $value;

                    self::dataHelper($key, $value);
                }
            }
        }

        self::resolveRefs($parsed);
    }

    /**
     * Supports for ${<VARNAME>}
     *
     * @param array $vars Associative array
     * @return void
     */
    protected static function resolveRefs($vars)
    {
        foreach ($vars as $key => $value) {
            if (! $value || strpos($value, '${') === false) {
                continue;
            }

            $value = preg_replace_callback('~\${(\w+)}~', function ($v) {
                return (null === $ref = self::getEnv($v[1])) ? $v[0] : $ref;
            }, $value);

            self::dataHelper($key, $value);
        }
    }

    /**
     * Get/Set Raw Data
     *
     * @param null|string $key
     * @param null|mixed $value
     * @param null|int $mode
     * @return array|false|mixed|string|void
     */
    protected static function dataHelper($key = null, $value = null, $mode = null)
    {
        // If key is one of the MOD we overwrite this retrieval, else we will use what is self.
        $mode = self::isValidMode($mode) ? $mode : self::$mode;

        // Remove some carriage returns
        if (null !== $value) {
            $value = str_replace(["\n", "\t", "\r"], '', $value);
        }

        if ($mode === self::MODE_PUTENV) {
            if ($key && ! isset($value)) return getenv($key);
            if (! empty($key) && ! empty($value)) putenv("$key=$value");
            if (! ($key && $value)) return self::getEnvReader();
        }

        if ($mode === self::MODE_ENV) {
            if ($key && ! isset($value)) return isset($_ENV[$key]) ? $_ENV[$key] : null;
            if (! ($key && $value)) return $_ENV;
            if (! empty($key) && ! empty($value)) $_ENV[$key] = $value;
        }

        if ($mode === self::MODE_SERVER) {
            if ($key && ! isset($value)) return isset($_SERVER[$key]) ? $_SERVER[$key] : null;
            if (! ($key && $value)) return $_SERVER;
            if (! empty($key) && ! empty($value)) $_SERVER[$key] = $value;
        }
    }

    /**
     * Throw an exception if we are not able to get the list of environment
     * variables' names.
     *
     * @throws Exception
     */
    protected static function assertEnvListAvailable()
    {
        if (empty(self::readAll())) {
            $variablesOrder = ini_get('variables_order');
            if (strpos($variablesOrder, 'E') === false) {
                throw new Exception(
                    'Cannot get a list of the current environment variables. '
                    . 'Make sure the `variables_order` variable in php.ini '
                    . 'contains the letter "E".',
                    1496164061
                );
            }
        }
    }

    /**
     * Get All Data.
     *
     * @param int $mode
     * @return mixed
     */
    public static function readAll($mode = null)
    {
        return self::dataHelper(null, null,$mode ?: self::$mode);
    }

    /**
     * GET Environment. Automatically resolve the type of data will return.
     *
     * @param string $varName
     * @param mixed $default
     * @return mixed
     */
    public static function get($varName, $default = null)
    {
        if (! is_null($value = self::getEnv($varName, $default))) {
            if (($v = self::toJson($value)) && is_object($v)) {
                return $v;
            }
            if (($v = self::toArray($value)) && is_array($v)) {
                return $v;
            }

            return $value;
        }

        return $default;
    }

    /**
     * Retrieve the value of the specified environment variable, translating
     * values of 'true', 'false', and 'null' (case-insensitive) to their actual
     * non-string values.
     *
     * @param string $varName
     * @param mixed $default
     * @return mixed
     */
    public static function getEnv($varName, $default = null)
    {
        $originalValue = self::dataHelper($varName);

        if ($originalValue === false) {
            return false;
        }

        if (is_numeric($originalValue)) {
            return strpos($originalValue, '.') ? floatval($originalValue) : intval($originalValue);
        }

        $trimmedValue = trim($originalValue);

        // Check for these values.
        $special = ['true' => true, 'false' => false, 'null' => null, '' => $default];
        if (array_key_exists($env = strtolower($trimmedValue), $special)) {
            return $special[$env];
        }

        return $trimmedValue;
    }

    /**
     * @param string $varName
     * @param mixed $value
     * @param boolean $override
     * @return void
     */
    public static function setEnv($varName, $value, $override = false)
    {
        if (! $override && null !== self::getEnv($varName)) {
            return;
        }

        // Reverse
        if (! is_array($value)) {
            $special = [true => 'true', false => 'false', null => 'null', '' => ''];
            if (array_key_exists($env = trim(strtolower($value)), $special)) {
                $value = $special[$env];
            }
        }

        // Check if array
        if (is_array($value)) {
            $value = self::arrayToString($value);
        }

        static::dataHelper($varName, $value);
    }

    /**
     * Require a environment Name
     *
     * @param string $varName
     * @return string
     *
     * @throws Exception
     */
    public static function requireEnv($varName)
    {
        $value = self::getEnv($varName);
        
        if ($value === null) {
            $message = 'Required environment variable: ' . $varName . ', not found.';
            throw new Exception($message);
        }
        
        return $value;
    }

    /**
     * Get value as a json. Also, automatically called by getArray.
     *
     * @param string $varName
     * @param mixed $default
     * @param null|boolean $assoc
     * @return mixed|null
     */
    public static function getJson($varName, $default = null, $assoc = null)
    {
        return ($value = self::toJson(self::getEnv($varName), $assoc)) ? $value : $default;
    }

    /**
     * Get Array Value. Separated by a comma
     *
     * @param string $varName
     * @param array $default
     * @param boolean $asPrefix
     * @return array
     */
    public static function getArray($varName, $default = [], $asPrefix = false)
    {
        if ($asPrefix) {
            try {
                return self::getPrefixedArray($varName);
            } catch (Exception $e) {}
        }

        return ($value = self::toArray(self::getEnv($varName))) ? $value : $default;
    }

    /**
     * Get an associative array of data, built from environment variables whose
     * names begin with the specified prefix (such as 'MY_DATA_'). If none are
     * found, an empty array will be returned.
     *
     * The values 'true', 'false', and 'null' (case-insensitive) will be
     * converted to their non-string values. See Env::get().
     *
     * @param string $prefix The prefix to look for, in the list of defined
     *     environment variables' names. Must not be empty.
     * @return array
     *
     * @throws Exception
     */
    protected static function getPrefixedArray($prefix)
    {
        self::assertEnvListAvailable();
        
        if (empty($prefix)) {
            throw new Exception(
                'You must provide a non-empty prefix to search for.',
                1496164608
            );
        }
        
        $results = [];
        
        foreach (array_keys($_ENV) as $name) {
            if (self::startsWith($name, $prefix)) {
                $nameAfterPrefix = substr($name, strlen($prefix));
                $results[$nameAfterPrefix] = self::getEnv($name);
            }
        }
        
        return $results;
    }
    
    /**
     * Retrieve a required array variable.
     *
     * @param string $varName
     * @return array
     *
     * @throws Exception
     */
    public static function requireArray($varName)
    {
        self::requireEnv($varName);
        
        return self::getArray($varName);
    }
    
    /**
     * See if the given string starts with other given string.
     *
     * @param string $subject
     * @param array|string $needle
     * @param boolean $case_insensitive
     * @return bool
     */
    protected static function startsWith($subject, $needle, $case_insensitive = true)
    {
        $needle = is_array($needle) ? $needle : [$needle];

        for ($i = 0; $c = count($needle), $i < $c; $i++) {
            if (0 === substr_compare($subject, $needle[$i], 0, strlen($needle[$i]), $case_insensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Try to convert to Array.
     *
     * Validates a string if it's a valid json array or object (converted to array).
     * Validates a string if its comma separated with 2 items.
     *
     * @param string $value
     * @return null|array
     */
    protected static function toArray($value)
    {
        if ($value) {
            if ($v = self::toJson($value, true)) {
                return $v;
            } elseif (is_array($v = explode(',', $value)) && count($v) > 1 && ! empty($v)) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Try to convert to JSON Object/Array.
     *
     * @param string $value
     * @param null|boolean $assoc
     * @return null|array|object
     */
    protected static function toJson($value, $assoc = null)
    {
        if ($value && self::isJson($value)) {
            $decoded = json_decode($value, $assoc);

            if ((is_array($decoded) || is_object($decoded)) && json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * See if the given string ends with other given string.
     *
     * @param string $subject
     * @param array|string $needle
     * @param boolean $case_insensitive
     * @return bool
     */
    protected static function endsWith($subject, $needle, $case_insensitive = true)
    {
        $needle = is_array($needle) ? $needle : [$needle];

        for ($i = 0; $c = count($needle), $i < $c; $i++) {
            if (0 === substr_compare($subject, $needle[$i], -strlen($needle[$i]), null, $case_insensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param array $haystack
     * @return string
     */
    protected static function arrayToString($haystack)
    {
        $is_assoc = array_keys($haystack) !== range(0, count($haystack) - 1);

        if ($is_assoc) {
            return json_encode($haystack);
        } else {
            return implode(',', $haystack);
        }
    }

    /**
     * Check if value is serialized.
     *
     * @param string $value
     * @return bool
     */
    protected static function isJson($value)
    {
        if (self::startsWith($value, ['[','{']) && self::endsWith($value, ['}',']'])) {
            $decoded = json_decode($value);

            return (is_object($decoded) || is_array($decoded)) && (json_last_error() === JSON_ERROR_NONE);
        }

        return false;
    }

    /**
     * Check if mode is valid
     *
     * @param int $mode
     * @return bool
     */
    private static function isValidMode($mode)
    {
        $constants = (new ReflectionClass(self::class))->getConstants();

        return isset($mode) && in_array($mode, array_values($constants));
    }

    /**
     * Parse phpinfo to associative array.
     *
     * @param $flags
     * @return array|false
     */
    protected static function getEnvReader($flags = INFO_ENVIRONMENT)
    {
        // Fetch PHP config
        ob_start();
        phpinfo($flags);
        $contents = ob_get_contents();
        ob_end_clean();

        // Pairs
        $keys = [];
        $values = [];

        // <td[^\<]+>([^\<]+)<\/td>
        if (preg_match_all('/<td.*?>(.*?)<\/td>/m', $contents, $arr, PREG_SET_ORDER)) {
            foreach ($arr as $i => $r) {
                $arr[$i] = trim($r[1]);
            }

            if (($count = count($arr)) % 2 > 0) {
                throw new BadMethodCallException('Number of values in to_assoc must be even.');
            }

            for ($i = 0; $i < $count; $i += 2) {
                $keys[] = array_shift($arr);
                $values[] = array_shift($arr);
            }
        }

        return array_combine($keys, $values);
    }
}
