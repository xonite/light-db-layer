<?php
namespace LightDBLayer\Utility;

trait DBCaseTranslator
{
    private function toDbCase($input): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match === strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    private function toCamelCase(string $input, array $noStrip = []): string
    {
        $str = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $input);
        $str = trim($str);
        $str = ucwords($str);
        $str = str_replace(" ", "", $str);
        $str = lcfirst($str);

        return $str;
    }
}
