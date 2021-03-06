<?php
/**
 * spider
 * @copyright 2014 Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace ddliu\spider\Pipe;

class IconvPipe extends BasePipe {
    public static $TRANSFER_ENCODINGS = array(
        'GB2312' => 'GBK',
    );

    public function __construct($options = array())
    {
        $this->options = array_merge([
            'from' => 'AUTO',
            'to' => 'UTF-8',
        ], $options);
    }

    public function run($spider, $task) {
        if ($this->options['from'] === 'AUTO') {
            $fromEncoding = $this->detectEncoding($task['content']);
            if (!$fromEncoding) {
                throw new PipeException('Detect encoding failed');
            }
        } else {
            $fromEncoding = $this->options['from'];
        }

        if ($fromEncoding !== $this->options['to']) {
            $task['content'] = iconv($fromEncoding, $this->options['to'], $task['content']);
        }
    }

    protected function detectEncoding($content) {
        $encoding = false;
        if (preg_match('#<meta\s+[^>]*\s*charset\s*=\s*["\']([a-z0-9-]{3,15})["\']#Uis', $content, $match)) {
            $encoding = $match[1];
        } elseif (preg_match('#<meta\s+http-equiv="Content-Type"\s+content="[^"]+charset=([a-z0-9-]{3,15})"#Uis', $content, $match)) {
            $encoding = $match[1];
        } else {
            $encoding = mb_detect_encoding($content);
        }

        if ($encoding) {
            $encoding = $this->normalizeEncoding($encoding);

            if (isset(self::$TRANSFER_ENCODINGS[$encoding])) {
                $encoding = self::$TRANSFER_ENCODINGS[$encoding];
            }

            return $encoding;
        }

        return false;
    }

    protected function normalizeEncoding($encoding) {
        return strtoupper(trim($encoding));
    }
}