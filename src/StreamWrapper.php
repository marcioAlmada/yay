<?php declare(strict_types=1);

namespace Yay;

use function yay_parse;

use
    SplFileObject,
    SplFileInfo
;

final class StreamWrapper {
    const
        SCHEME = 'yay',
        STAT_MTIME_NUMERIC_OFFSET = 9,
        STAT_MTIME_ASSOC_OFFSET = 'mtime'
    ;

    protected static
        $registered = false,
        $engine
    ;

    protected
        $resource
    ;

    static function register(Engine $engine)
    {
        if (true === self::$registered) return;

        if (@stream_wrapper_register(self::SCHEME, __CLASS__) === false)
            throw new YayParseError(
                'A handler has already been registered for the ' .
                self::SCHEME . ' protocol.'
            );

        self::$engine = $engine;
        self::$registered = true;
    }

    static function unregister()
    {
        if (!self::$registered) {
            if (in_array(self::SCHEME, stream_get_wrappers()))
                throw new YayParseError(
                    'The URL wrapper for the protocol ' . self::SCHEME .
                    ' was not registered with this version of YAY.'
                );

            return;
        }

        if (!@stream_wrapper_unregister(self::SCHEME))
            throw new YayParseError(
                'Failed to unregister the URL wrapper for the ' . self::SCHEME .
                ' protocol.'
            );

        self::$engine = null;
        self::$registered = false;
    }

    function stream_open(string $path, string $mode, int $flags, &$opened_path) : bool
    {
        $path = preg_replace('#^' . self::SCHEME . '://#', '', $path);

        if (STREAM_USE_PATH & $flags && $path[0] !== '/')
            $path = dirname(debug_backtrace()[0]['file']) . '/' . $path;

        $fileMeta = new SplFileInfo($path);

        if (! $fileMeta->isReadable()) return false;

        $opened_path = $path;

        $source = self::$engine->expand(file_get_contents($fileMeta->getRealPath()), $fileMeta->getRealPath());

        $this->resource = fopen('php://memory', 'rb+');
        fwrite($this->resource, $source);
        rewind($this->resource);

        return true;
    }

    function stream_close() {
        fclose($this->resource);
    }

    function stream_read($length) : string {
        $source =
            ! feof($this->resource)
                ? fread($this->resource, $length)
                : ''
        ;

        return $source;
    }

    function stream_eof() : bool { return feof($this->resource); }

    function stream_stat() : array
    {
        $stat = fstat($this->resource);
        if ($stat) {
            $stat[self::STAT_MTIME_ASSOC_OFFSET]++;
            $stat[self::STAT_MTIME_NUMERIC_OFFSET]++;
        }
        return $stat;
    }

    function url_stat() { $this->notImplemented(__FUNCTION__); }

    function stream_write() { $this->notImplemented(__FUNCTION__); }

    function stream_truncate() { $this->notImplemented(__FUNCTION__); }

    function stream_metadata() { $this->notImplemented(__FUNCTION__); }

    function stream_tell() { $this->notImplemented(__FUNCTION__); }

    function stream_seek() { $this->notImplemented(__FUNCTION__); }

    function stream_flush() { $this->notImplemented(__FUNCTION__); }

    function stream_cast() { $this->notImplemented(__FUNCTION__); }

    function stream_lock() { $this->notImplemented(__FUNCTION__); }

    function stream_set_option() { $this->notImplemented(__FUNCTION__); }

    function unlink() { $this->notImplemented(__FUNCTION__); }

    function rename() { $this->notImplemented(__FUNCTION__); }

    function mkdir() { $this->notImplemented(__FUNCTION__); }

    function rmdir() { $this->notImplemented(__FUNCTION__); }

    function dir_opendir() { $this->notImplemented(__FUNCTION__); }

    function dir_readdir() { $this->notImplemented(__FUNCTION__); }

    function dir_rewinddir() { $this->notImplemented(__FUNCTION__); }

    function dir_closedir() { $this->notImplemented(__FUNCTION__); }

    private function notImplemented(string $from) {
        throw new YayParseError(__CLASS__ . "->{$from} is not implemented.");
    }
}
