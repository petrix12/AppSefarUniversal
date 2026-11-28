<?php

namespace App\Services;

class AppEmailTheme
{
    public function colors(): array
    {
        $defaults = ['primary' => '#093143', 'primary-dark' => '#001b27', 'primary-soft' => '#e8f2f5',
            'accent' => '#a17f48', 'accent-light' => '#dbba72', 'surface' => '#ffffff', 'page' => '#f4f7f9',
            'text' => '#093143', 'muted' => '#607783', 'radius' => '8px', 'font-family' => "'Lato', Arial, sans-serif"];
        $path = public_path('css/sefar.css');
        $css = is_file($path) ? file_get_contents($path, false, null, 0, 8192) : '';
        if (preg_match('/:root\s*\{([^}]+)\}/', $css, $root)) {
            foreach ($defaults as $key => &$value) {
                if (preg_match('/--sefar-'.preg_quote($key, '/').'\s*:\s*([^;]+);/', $root[1], $match)) $value = trim($match[1]);
            }
        }
        return $defaults;
    }

    public function css(): string
    {
        $c = $this->colors();
        return ".sefar-email-content,.sefar-email-content *{box-sizing:border-box;}"
            .".sefar-email-content{font-family:{$c['font-family']};font-size:16px;line-height:1.65;color:{$c['text']};}"
            .".sefar-email-content h1,.sefar-email-content h2,.sefar-email-content h3{color:{$c['primary']};line-height:1.3;margin:0 0 16px;font-weight:800;}"
            .".sefar-email-content h1{font-size:26px;}.sefar-email-content h2{font-size:22px;}.sefar-email-content h3{font-size:18px;}"
            .".sefar-email-content p{margin:0 0 18px;}.sefar-email-content a{color:{$c['primary']};}"
            .".sefar-email-content img{max-width:100%;height:auto;}.sefar-email-content table{max-width:100%;}"
            .".sefar-email-content td,.sefar-email-content th{overflow-wrap:break-word;word-break:break-word;}"
            .".sefar-email-content .button{background-color:{$c['primary']};border:12px solid {$c['primary']};border-radius:{$c['radius']};color:#ffffff;text-decoration:none;font-weight:700;}"
            .".sefar-email-content .panel{background:{$c['primary-soft']};border-left:3px solid {$c['accent']};padding:18px;}"
            .".sefar-email-content .subcopy{font-size:12px;color:{$c['muted']};overflow-wrap:anywhere;word-break:break-all;}";
    }
}
