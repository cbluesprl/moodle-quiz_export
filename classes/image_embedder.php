<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace quiz_export;

use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * @package   quiz_export
 * @copyright 2026 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_embedder {

    const REMOTE_TIMEOUT = 5;
    const MAX_ROUTING_SEGMENTS = 4;
    protected $cache = [];

    /**
     * @param string $html The rendered HTML.
     * @return string The HTML with embedded images.
     */
    public function embed($html) {
        if (empty($html)) {
            return $html;
        }

        return preg_replace_callback(
            '/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])(.*?)\2/is',
            function ($matches) {
                $datauri = $this->to_data_uri(html_entity_decode($matches[3], ENT_QUOTES, 'UTF-8'));
                if ($datauri === null) {
                    return $matches[0];
                }
                return $matches[1] . $matches[2] . $datauri . $matches[2];
            },
            $html
        );
    }

    /**
     * @param string $url The image URL, as written in the HTML.
     * @return string|null The data URI, or null when the image could not be read.
     */
    protected function to_data_uri($url) {
        $url = trim($url);
        if ($url === '' || stripos($url, 'data:') === 0) {
            return null;
        }

        if (array_key_exists($url, $this->cache)) {
            return $this->cache[$url];
        }

        $file = $this->find_stored_file($url);
        if ($file !== null) {
            $datauri = 'data:' . $file->get_mimetype() . ';base64,' . base64_encode($file->get_content());
        } else {
            $datauri = $this->fetch_remote($url);
        }

        $this->cache[$url] = $datauri;

        return $datauri;
    }

    /**
     * @param string $url The image URL.
     * @return stored_file|null The file, or null when the URL is not a local file URL.
     */
    protected function find_stored_file($url) {
        $segments = $this->extract_path_segments($url);
        if ($segments === null || count($segments) < 4) {
            return null;
        }

        $contextid = (int) array_shift($segments);
        $component = array_shift($segments);
        $filearea = array_shift($segments);
        if (empty($contextid) || $component === '' || $filearea === '') {
            return null;
        }

        $fs = get_file_storage();

        $attempts = min(count($segments), self::MAX_ROUTING_SEGMENTS + 1);
        for ($dropped = 0; $dropped < $attempts; $dropped++) {
            $relativepath = implode('/', array_slice($segments, $dropped));
            $file = $fs->get_file_by_hash(sha1("/{$contextid}/{$component}/{$filearea}/{$relativepath}"));
            if ($file && !$file->is_directory()) {
                return $file;
            }
        }

        debugging("quiz_export: could not resolve the local image {$url}", DEBUG_DEVELOPER);

        return null;
    }

    /**
     * @param string $url The image URL.
     * @return array|null The decoded path segments, or null when the URL is not served by Moodle.
     */
    protected function extract_path_segments($url) {
        global $CFG;

        $parts = parse_url($url);
        if ($parts === false || !empty($parts['scheme']) && !preg_match('/^https?$/i', $parts['scheme'])) {
            return null;
        }

        $wwwroot = parse_url($CFG->wwwroot);

        if (!empty($parts['host']) && strcasecmp($parts['host'], $wwwroot['host'] ?? '') !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '';
        $prefix = rtrim($wwwroot['path'] ?? '', '/');
        if ($prefix !== '' && strpos($path, $prefix) === 0) {
            $path = substr($path, strlen($prefix));
        }

        if (!preg_match('#^/(?:webservice/)?(pluginfile|tokenpluginfile|draftfile)\.php(/.*)?$#i', $path, $matches)) {
            return null;
        }

        $arguments = $matches[2] ?? '';
        if ($arguments === '' && !empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $arguments = $query['file'] ?? '';
        }

        $segments = [];
        foreach (explode('/', trim($arguments, '/')) as $segment) {
            $segment = urldecode($segment);
            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        if (strtolower($matches[1]) === 'tokenpluginfile') {
            array_shift($segments);
        }

        return $segments;
    }

    /**
     * @param string $url The absolute image URL.
     * @return string|null The data URI, or null when the download failed or returned something
     *                     that is not an image.
     */
    protected function fetch_remote($url) {
        global $CFG;

        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $content = $curl->get($url, [], [
            'CURLOPT_CONNECTTIMEOUT' => self::REMOTE_TIMEOUT,
            'CURLOPT_TIMEOUT' => self::REMOTE_TIMEOUT,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 3,
        ]);

        if ($curl->get_errno() || empty($content)) {
            debugging("quiz_export: could not download the remote image {$url}", DEBUG_DEVELOPER);
            return null;
        }

        $mimetype = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

        if (strpos((string) $mimetype, 'image/') !== 0) {
            debugging("quiz_export: {$url} did not return an image", DEBUG_DEVELOPER);
            return null;
        }

        return 'data:' . $mimetype . ';base64,' . base64_encode($content);
    }
}