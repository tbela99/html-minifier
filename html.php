<?php

/**
 * @package     GZip.HTML
 * @subpackage  HTML.Minify
 *
 * @copyright   Copyright (C) 2013 Thierry Bela
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

class PlgSystemHTML extends JPlugin {

    public function onAfterRender() {

        if (!$this->params->get('minifyhtml')) {

            return;
        }

        $app = JFactory::getApplication();

        if (!$app->isSite()) {

            return;
        }

        $body = $app->getBody();

        $scripts = [];
        $tags = ['script', 'link', 'style', 'pre'];

        $body = preg_replace_callback('#(<(('.implode(')|(', $tags).'))[^>]*>)(.*?)</\2>#si', function ($matches) use(&$scripts, $tags) {

            $match = $matches[count($tags) + 3];
            $hash = '--***-' . crc32($match) . '-***--';

            $scripts[$hash] = $match;

            return $matches[1] . $hash . '</'.$matches[2].'>';
            
        }, $body);
        
        $self = [
            
            'meta',
            'link',
            'br',
            'base',
            'input'
        ];
        
        $body = str_replace(JURI::getInstance()->getScheme().'://', '//', $body);
        
        $body = preg_replace_callback('#<html(\s[^>]+)?>(.*?)</head>#si', function ($matches) {
            
            return '<html'.$matches[1].'>'. preg_replace('#>[\r\n\t ]+<#s', '><', $matches[2]).'</head>';
        }, $body);
        
        //remove optional ending tags (see http://www.w3.org/TR/html5/syntax.html#syntax-tag-omission )
        $remove = [
            '</option>', '</li>', '</dt>', '</dd>', '</tr>', '</th>', '</td>', '</thead>', '</tbody>', '</tfoot>', '</colgroup>'
        ];
        
        if(stripos($body, '<!DOCTYPE html>') !== false) {
            
            $remove = array_merge($remove, [
                
                '<head>',
                '</head>',
                '<body>',
                '</body>',
                '<html>',
                '</html>'
            ]);
        }
        
        $body = str_ireplace($remove, '', $body);

        // minify html
        //remove redundant (white-space) characters
        $replace = [
            
            '#<!DOCTYPE ([^>]+)>[\n\s]+#si' => '<!DOCTYPE $1>',
            '#<(('.implode(')|(', $self).'))(\s[^>]*?)?/>#si' => '<$1$'.(count($self) + 2).'>',
            //remove tabs before and after HTML tags
            '#<!--.*?-->#s' => '',
            '/\>[^\S ]+/s' => '>',
            '/[^\S ]+\</s' => '<',
            //shorten multiple whitespace sequences; keep new-line characters because they matter in JS!!!
            '/([\t ])+/s' => ' ',
            //remove leading and trailing spaces
            '/(^([\t ])+)|(([\t ])+$)/m' => '',
            //remove empty lines (sequence of line-end and white-space characters)
            '/[\r\n]+([\t ]?[\r\n]+)+/s' => "\n",
            //remove quotes from HTML attributes that does not contain spaces; keep quotes around URLs!
            '~([\r\n\t ])?([a-zA-Z0-9:]+)=(["\'])([^\s\3]+)\3([\r\n\t ])?~' => '$1$2=$4$5', //$1 and $4 insert first white-space character found before/after attribute
            // <p > => <p>
            '#<([^>]+)([^/])\s+>#s' => '<$1$2>'
        ];

        $body = preg_replace(array_keys($replace), array_values($replace), $body);
        
        $body = preg_replace_callback('#(\S)[\r\n\t ]+<(/?)#s', function ($matches) {
            
            if($matches[2] == '/') {
                
                return $matches[1].'</';
            }

            return $matches[1].' <';
            
        }, $body);

        if (!empty($scripts)) {

            $body = str_replace(array_keys($scripts), array_values($scripts), $body);
        }

        $app->setBody($body);
    }
}
