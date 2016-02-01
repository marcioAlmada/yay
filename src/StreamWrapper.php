<?php declare(strict_types=1);

namespace Yay;

use function yay_parse;

use
    SplFileObject,
    SplFileInfo
;

final class StreamWrapper {
    const
        SCHEME = 'yay'
    ;

    protected static
        $registered = false
    ;

    protected
        /**
         * @type SplFileInfo
         */
        $fileMeta,
        /**
         * @type SplFileObject
         */
        $file
    ;

    static function register()
    {
        if (true === self::$registered) return;

        if (@stream_wrapper_register(self::SCHEME, __CLASS__) === false)
            throw new YayException(
                'A handler has already been registered for the ' .
                self::SCHEME . ' protocol.'
            );

        self::$registered = true;
    }

    static function unregister()
    {
        if (!self::$registered) {
            if (in_array(self::SCHEME, stream_get_wrappers()))
                throw new YayException(
                    'The URL wrapper for the protocol ' . self::SCHEME .
                    ' was not registered with this version of YAY.'
                );

            return;
        }

        if (!@stream_wrapper_unregister(self::SCHEME))
            throw new YayException(
                'Failed to unregister the URL wrapper for the ' . self::SCHEME .
                ' protocol.'
            );

        self::$registered = false;
    }

    function stream_open(string $path, string $mode, int $flags, &$opened_path) : bool
    {
        $path = preg_replace('#^' . self::SCHEME . '://#', '', $path);

        if (STREAM_USE_PATH & $flags && $path[0] !== '/')
            $path = dirname(debug_backtrace()[0]['file']) . '/' . $path;

        $this->fileMeta = new SplFileInfo($path);

        $opened_path = $path;

        if (! $this->fileMeta->isReadable()) return false;

        $this->file = $this->fileMeta->openFile($mode);

        return true;
    }

    function stream_close(){}

    function stream_read($lengh) : string {
        return
            ! $this->file->eof()
                ? yay_parse($this->file->fread($lengh))
                : ''
        ;
    }

    function stream_eof() : bool { return $this->file->eof(); }

    function stream_stat() : array
    {
        $stat =
            $this->file ? [
                'dev' => 0,
                'ino' => 0,
                'mode' => 'rb',
                // 'mode' => $this->file->getMode(),
                'nlink' => 0,
                'uid' => $this->file->getOwner(),
                'gid' => $this->file->getGroup(),
                'rdev' => 0,
                'size' => $this->file->getSize(),
                'atime' => $this->file->getATime(),
                'mtime' => $this->file->getMTime(),
                'ctime' => $this->file->getCTime(),
                'blksize' => -1,
                'blocks' => -1
            ] : []
        ;

        return array_merge(array_values($stat), $stat);
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
        throw new YayException(__CLASS__ . "->{$from} is not implemented.");
    }
}
