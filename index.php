<?php

require_once 'Env.php';

Env::setMode(Env::MODE_ENV);
Env::loadEnvFile(__DIR__ . '/.env_local');

echo <<<STYLESHEET
<style>
    pre, pre code {
        font-family: "SF Mono", Consolas, Menlo, "Liberation Mono", Courier, monospace;
        font-size: 12px;
        line-height: 18px;
    }
    pre code {
        display: block;
        background: #efefef;
        color: #000;
        padding: 10px;
    }
    .src_text {
        color: #008000;
    }
    .src_type {
        color: #9e0303;
    }
</style>
STYLESHEET;


echo '<pre>';

$dd = [
    ['Env::getArray', 'TEST_', [], true],
    ['Env::get', 'TEST_JSON'],
    ['Env::get', 'TEST_JSON_ARRAY'],
    ['Env::get', 'TEST_ARRAY'],
    ['Env::get', 'TEST_FALSE'],
    ['Env::get', 'TEST_TRUE'],
    ['Env::get', 'TEST_NULL'],
    ['Env::get', 'TEST_REF'],
    ['Env::get', 'TEST_WORLD'],
    ['Env::get', 'TEST_INT'],
    ['Env::get', 'TEST_FLOAT'],
    ['Env::getArray', 'MAIL_', [], true],
    ['Env::get', 'MAIL_HOST'],
    ['Env::get', 'MAIL_SMTPAUTH'],
    ['Env::get', 'MAIL_PORT'],
    ['Env::get', 'MAIL_USERNAME'],
    ['Env::get', 'MAIL_PASSWORD'],
];

foreach ($dd as $d) {
    $method = array_shift($d);
    $args = $d;

    ob_start();
    var_dump(call_user_func_array($method, $args));
    $out = ob_get_contents();
    ob_end_clean();

    $out = preg_replace('/=>\n\s\s/', ' => ', $out);

    // Quotes to GREEN
    $out = preg_replace('/"[^"]\W\w.+"|"[^"]+"/', '<span class="src_text">$0</span>', $out);

    // Bold variable type(?)
    $out = preg_replace('/([\S]+[\s]?\(\S+\))/', '<strong>$0</strong>', $out);

    // (?)
    $out = preg_replace('/\((\S+)\)/', '(<span class="src_type">$1</span>)', $out);

    echo sprintf("\n<strong>▲ %s('%s')</strong>\n\n<code>%s</code>", $method, $args[0], $out);
}


$mails = Env::get('TEST_FALSE');
print_r($mails);
echo '</pre>';