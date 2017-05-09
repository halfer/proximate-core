<?php

/**
 * Some file services useful for the queue module
 */

namespace Proximate\Service;

use Proximate\Exception\NotWritable as NotWritableException;
use Proximate\Exception\File as FileException;

class File
{
    protected $logPath;

    public function __construct($logPath = null)
    {
        $this->logPath = $logPath;
    }

    public function fileExists($filename)
    {
        return file_exists($filename);
    }

    public function isDirectory($filename)
    {
        return is_dir($filename);
    }

    public function filePutContents($filename, $data)
    {
        // Deliberately using the silent operator here. The purpose of this is to create
        // a file if it is not there but is creatable, so that is_writable succeeds. We do
        // not care if it fails.
        @touch($filename);

        if (!is_writable($filename)) {
            throw new NotWritableException(
                sprintf("Could not write to file `%s`", $filename)
            );
        }

        $ok = file_put_contents($filename, $data);
        $this->writeLog("Writing to file `$filename`", $ok);

        return $ok;
    }

    public function fileGetContents($filename)
    {
        return file_get_contents($filename);
    }

    public function glob($pattern)
    {
        return glob($pattern);
    }

    public function rename($oldname, $newname)
    {
        $ok = @rename($oldname, $newname);
        if (!$ok)
        {
            throw new NotWritableException(
                sprintf("Could not rename `%s` to `%s`", $oldname, $newname)
            );
        }
        $this->writeLog("Renaming `$oldname` to `$newname`", $ok);
    }

    public function copy($pattern, $targetDir)
    {
        $files = $this->glob($pattern);
        foreach ($files as $file)
        {
            $targetFile = $targetDir . DIRECTORY_SEPARATOR . basename($file);
            $ok = @copy($file, $targetFile);
            if (!$ok) {
                throw new NotWritableException(
                    sprintf("Could not copy to file target `%s`", $targetFile)
                );
            }
        }
        $this->writeLog(
            sprintf(
                "Attempted to copy %d files from `%s` to `%s`",
                count($files),
                $pattern,
                $targetDir
            )
        );
    }

    public function mkdir($pathname)
    {
        $ok = @mkdir($pathname);
        if (!$ok)
        {
            throw new NotWritableException(
                sprintf("Could not create folder `%s`", $pathname)
            );
        }
        $this->writeLog("Creating directory `$pathname`", $ok);
    }

    public function unlinkFile($path)
    {
        if (!is_writable($path)) {
            throw new NotWritableException(
                sprintf("Could not remove file `%s`", $path)
            );
        }

        $ok = unlink($path);
        if (!$ok)
        {
            throw new FileException("Failed to unlink `%s`", $path);
        }
        $this->writeLog("Unlinking file `$path`", $ok);

        return $ok;
    }

    /**
     * @param string $folderPath
     */
    public function unlinkFiles($folderPath)
    {
        foreach ($this->glob($folderPath . DIRECTORY_SEPARATOR . '*') as $file)
        {
            $this->unlinkFile($file);
        }
    }

    /**
     * Deletes a folder
     *
     * @param string $path
     * @throws NotWritableException
     * @throws FileException
     */
    public function rmDir($path)
    {
        if (!is_writable($path)) {
            throw new NotWritableException(
                sprintf("Could not remove directory `%s`", $path)
            );
        }

        $ok = @rmdir($path);
        if (!$ok)
        {
            throw new FileException(
                sprintf("Deleting folder `%s` failed", $path)
            );
        }
        $this->writeLog("Removing directory `$path`", $ok);

        return $ok;
    }

    public function basename($path)
    {
        return basename($path);
    }

    protected function writeLog($message, $ok = null)
    {
        // Only write if we have a log path
        if (!$this->logPath)
        {
            return;
        }

        // Compose log message
        $final = $message;
        if (is_bool($ok))
        {
            $final .= $ok ? ' (OK)' : ' (Failed)';
        }
        $final .= "\n";

        file_put_contents(
            $this->logPath,
            $final,
            FILE_APPEND
        );
    }
}
