<?php

class ComposerLockDiff
{
    private $path;

    private $options;

    public function __construct($path, $options = [])
    {
        $this->path = $path;
        foreach ($options as $key => $value) {
            if (empty($value)) {
                unset($options[$key]);
            }
        }
        $this->options = [
            'from' => array_key_exists('from', $options) ? $options['from'] : 'HEAD',
            'to' => array_key_exists('to', $options) ? $options['to'] : ''
        ];
    }

    public static function makeChangesAsMDTable($changes)
    {
        $options = [
            'capped' => false,
            'joint' => '|',
            'url_formatter' => 'urlFormatterMd',
        ];

        if (empty($changes)) return '';

        $opts = array_merge(['capped' => true, 'joint' => '+'], $options);

        $data = [];
        foreach ($changes as $repo => $change){
            $compare = empty($change['compare']) ? '' : sprintf('[%s](%s)', '...', $change['compare']);
            $data[$repo] = [
                $change['from'],
                $change['to'],
                $compare,
            ];
        }

        $titles = ['Changes', 'From', 'To', 'Compare'];

        $widths = [self::maxLength(array_merge(array($titles), array_keys($data)))];

        $count = count(reset($data));
        for ($i = 0; $i < $count; $i++) {
            $widths[] = max(strlen($titles[$i + 1]), self::maxLength(array_map(function ($k) use ($data, $i) {
                return $data[$k][$i];
            }, array_keys($data))));
        }

        if ($opts['capped']) {
            $lines[] = self::separatorLine($widths, $opts['joint']);
        }

        $lines[] = self::makeRow($titles, $widths);
        $lines[] = self::separatorLine($widths, $opts['joint']);

        foreach ($data as $key => $v) {
            $lines[] = self::makeRow(array_merge(array($key), $v), $widths);
        }

        if ($opts['capped']) {
            $lines[] = self::separatorLine($widths, $opts['joint']);
        }

        return implode(PHP_EOL, array_filter($lines)) . PHP_EOL . PHP_EOL;
    }

    private static function maxLength(array $array)
    {
        return max(array_map('strlen', $array));
    }

    private static function separatorLine($widths, $joint)
    {
        return $joint . implode($joint, array_map(function ($n) {
                return str_repeat('-', $n + 2);
            }, $widths)) . $joint;
    }

    private static function makeRow($data, $widths)
    {
        $fields = array();
        $count = max(array(count($data), count($widths)));
        for ($i = 0; $i < $count; $i++) {
            $value = ($i >= count($data)) ? '' : $data[$i];
            $width = ($i >= count($widths)) ? strlen($value) : $widths[$i];
            $fields[] = sprintf("%-{$width}s", $value);
        }
        return '| ' . implode(' | ', $fields) . ' |';
    }

    /**
     * @return array|string[]
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param array|string[] $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * @throws Exception
     */
    public function getChanges()
    {
        $cwd = getcwd();
        chdir($this->path);
        $changes = $this->diff(
            'packages',
            $this->options['from'],
            $this->options['to']
        );
        chdir($cwd);

        ksort($changes);
        return $changes;
    }

    /**
     * @param $key
     * @param $from
     * @param $to
     * @return array
     * @throws Exception
     */
    private function diff($key, $from, $to)
    {
        $packages = array();

        $data = $this->load($from);

        foreach ($data->$key as $pkg) {
            $packages[$pkg->name] = array(
                'from' => $this->version($pkg),
                'to' => '',
                'compare' => ''
            );
        }

        $data = $this->load($to);

        foreach ($data->$key as $pkg) {
            if (!array_key_exists($pkg->name, $packages)) {
                $packages[$pkg->name] = array(
                    'from' => '',
                    'to' => $this->version($pkg),
                    'compare' => ''
                );
                continue;
            }

            if ($packages[$pkg->name]['from'] == $this->version($pkg)) {
                unset($packages[$pkg->name]);
            } else {
                $packages[$pkg->name]['to'] = $this->version($pkg);
                $packages[$pkg->name]['compare'] = $this->makeCompareUrl($pkg, $packages);
            }
        }

        return $packages;
    }

    /**
     * @param $position
     * @param string $basePath
     * @return mixed
     * @throws Exception
     */
    private function load($position, $basePath = '')
    {
        $orig = $position;

        if (empty($basePath)) {
            $basePath = '.' . DIRECTORY_SEPARATOR;
        } else {
            $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        if (empty($position)) {
            $position = $basePath . 'composer.lock';
        }

        if ($this->isUrl($position)) {
            if (!in_array(parse_url($position, PHP_URL_SCHEME), stream_get_wrappers())) {
                throw new Exception("Error: no stream wrapper to open '$position'");
            }

            return $this->mustDecodeJson(file_get_contents($position), $position);
        }

        if (file_exists($position)) {
            return $this->mustDecodeJson(file_get_contents($position), $position);
        }

        if (strpos($orig, ':') === false) {
            $position .= ':' . $basePath . 'composer.lock';
        }

        $lines = [];

        exec('git show ' . escapeshellarg($position), $lines, $exit);

        if ($exit !== 0) {
            throw new Exception("Error: cannot open $orig or find it in git as $position");
        }

        return $this->mustDecodeJson(implode("\n", $lines), $position);
    }

    private function isUrl($string)
    {
        return filter_var($string, FILTER_VALIDATE_URL,
            FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED | FILTER_FLAG_PATH_REQUIRED);
    }

    /**
     * @param $json
     * @param $context
     * @return mixed
     * @throws Exception
     */
    private function mustDecodeJson($json, $context)
    {
        $data = json_decode($json);

        if (empty($data)) {
            throw new Exception("Error: contents from $context does not decode as json");
        }

        return $data;
    }

    private function version($pkg)
    {
        if (substr($pkg->version, 0, 4) == 'dev-') {
            $version = substr($pkg->source->reference, 0, 7) ?: '';
        } else {
            $version = (string)$pkg->version;
        }

        return $version;
    }

    private function makeCompareUrl($pkg, $diff)
    {
        $func = 'formatCompare' . ucfirst($this->getSourceRepoType((string)@$pkg->source->url));
        return call_user_func([$this, $func], $pkg->source->url, $diff[$pkg->name]['from'], $diff[$pkg->name]['to']);
    }

    private function getSourceRepoType($url)
    {
        if (!preg_match('/^http/i', $url)) {
            return 'unknown';
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST));

        if (strpos($host, 'github') !== false) {
            return 'github';
        } elseif (strpos($host, 'bitbucket') !== false) {
            return 'bitbucket';
        } elseif (strpos($host, 'gitlab') !== false) {
            return 'gitlab';
        }

        return 'unknown';
    }

    private function formatCompareUnknown($url, $from, $to)
    {
        return '';
    }

    private function formatCompareGithub($url, $from, $to)
    {
        return sprintf('%s/compare/%s...%s', preg_replace('/\.git$/', '', $url), urlencode($from), urlencode($to));
    }

    private function formatCompareBitbucket($url, $from, $to)
    {
        return sprintf('%s/branches/compare/%s%%0D%s', preg_replace('/\.git$/', '', $url), urlencode($from), urlencode($to));
    }

    private function formatCompareGitlab($url, $from, $to)
    {
        return sprintf('%s/compare/%s...%s', preg_replace('/\.git$/', '', $url), urlencode($from), urlencode($to));
    }
}