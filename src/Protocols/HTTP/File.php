<?php

namespace Te\Protocols\HTTP;

class File
{
    /**
     * @var string
     */
    private $fileName;

    /**
     * @var string
     */
    private $contentType;

    /**
     * @var string
     */
    private $ext;

    private static $tmpPath = '/tmp/';

    /**
     * @var string
     */
    private $tmpFileName;
    /**
     * @var string
     */
    private $nowFile;


    public function __construct(string $contentType, string $fileName, $content)
    {
        $this->contentType = $contentType;
        $this->fileName = $fileName;
        $this->ext = explode('.', $fileName)[1] ?? '';
        $this->tmpFileName = $this->makeName(self::$tmpPath);
        $this->save($content);
    }

    public function move($filePath): bool
    {
        $newFile = $this->makeName($filePath);
        $this->nowFile = $newFile;
        return rename($this->tmpFileName, $newFile);
    }

    private function save($content)
    {
        file_put_contents($this->tmpFileName, $content);
    }

    private function makeName($fileDir): string
    {
        if (!is_dir($fileDir)) {
            if (!mkdir($concurrentDirectory = $fileDir) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        return $fileDir . strRandom(32) . '.' . $this->ext;
    }

    /**
     * @return string
     */
    public static function getTmpPath(): string
    {
        return self::$tmpPath;
    }

    /**
     * @param string $tmpPath
     */
    public static function setTmpPath(string $tmpPath): void
    {
        if ($tmpPath === '') {
            return;
        }

        self::$tmpPath = $tmpPath;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @return string
     */
    public function getExt()
    {
        return $this->ext;
    }

    /**
     * @return string
     */
    public function getTmpFileName(): string
    {
        return $this->tmpFileName;
    }

    /**
     * @return string
     */
    public function getNowFile(): string
    {
        return $this->nowFile;
    }
}