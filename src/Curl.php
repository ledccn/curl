<?php

namespace Ledc\Curl;

/**
 * 自定义Curl
 */
class Curl extends \Curl\Curl
{
    /**
     * @var string The user agent name which is set when making a request
     */
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36';
    /**
     * @var array
     */
    protected array $files = [];
    /**
     * Cookies
     * @var array
     */
    protected array $_cookies = array();
    /**
     * 单例
     * @var Curl|null
     */
    protected static ?Curl $_instance = null;

    /**
     * 单例
     * @param bool $reset 是否重置Curl(默认true)
     * @return static
     */
    public static function getInstance(bool $reset = true): static
    {
        if (null === static::$_instance) {
            self::$_instance = new static();
        } else {
            // 重置
            if ($reset) {
                self::$_instance->reset();
            }
        }
        return self::$_instance;
    }

    /**
     * 添加待上传的文件
     * @param string $name 表单字段名
     * @param string $filename 文件名
     * @param string $metadata 文件的元数据
     * @param string|null $mime_type
     * @return self
     */
    public function addFile(string $name, string $filename, string $metadata, ?string $mime_type = null): self
    {
        $this->files[$name] = [$filename, $metadata, $mime_type];
        return $this;
    }

    /**
     * @param string $url
     * @param array $data
     * @return self
     */
    public function upload(string $url, array $data = []): self
    {
        $this->setOpt(CURLOPT_CUSTOMREQUEST, "POST");
        $this->setOpt(CURLOPT_URL, $url);
        $this->prepareUploadPayload($data);
        $this->files = [];
        $this->exec();
        return $this;
    }

    /**
     * @param array $data
     * @return void
     */
    private function prepareUploadPayload(array $data): void
    {
        $delimiter = uniqid('files');
        $this->setOpt(CURLOPT_POST, true);

        // invalid characters for "name" and "filename"
        static $disallow = ["\0", "\"", "\r", "\n"];

        $eol = "\r\n";
        $body = '';
        // 拼接文件流 build file parameters
        $body .= "--" . $delimiter . $eol;
        foreach ($this->files as $name => $item) {
            [$filename, $metadata, $mime_type] = $item;
            $mime_type = $mime_type ?: 'application/octet-stream';
            $body .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $filename . '"' . $eol;
            $body .= 'Content-Type: ' . $mime_type . $eol . $eol;
            $body .= $metadata . $eol;
        }
        // 构建正常参数 build normal parameters
        foreach ($data as $name => $content) {
            $name = str_replace($disallow, '_', $name);
            $body .= "--" . $delimiter . $eol;
            $body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
            $body .= $content . $eol;
        }
        $body .= "--" . $delimiter . "--" . $eol;

        $this->setHeader('Content-Type', 'multipart/form-data; boundary=' . $delimiter);
        $this->setHeader('Content-Length', strlen($body));
        $this->setOpt(CURLOPT_POSTFIELDS, $body);
    }

    /**
     * 公共设置
     * @param int $connectTimeout 尝试连接时等待的秒数
     * @param int $timeout 允许 cURL 函数执行的最长秒数
     * @return self
     */
    public function setCommon(int $connectTimeout = 10, int $timeout = 10): self
    {
        //在尝试连接时等待的秒数。设置为0，则无限等待
        $this->setOpt(CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        //允许 cURL 函数执行的最长秒数
        $this->setOpt(CURLOPT_TIMEOUT, $timeout);
        return $this;
    }

    /**
     * 设置不检查证书
     * @param bool $verifyPeer 验证对等证书
     * @param bool|int $verifyHost 检查名称
     * @return self
     */
    public function setSslVerify(bool $verifyPeer = false, bool|int $verifyHost = false): self
    {
        //false 禁止 cURL 验证对等证书
        $this->setOpt(CURLOPT_SSL_VERIFYPEER, $verifyPeer);
        //0 时不检查名称（SSL 对等证书中的公用名称字段或主题备用名称（Subject Alternate Name，简称 SNA）字段是否与提供的主机名匹配）
        $this->setOpt(CURLOPT_SSL_VERIFYHOST, $verifyHost);
        return $this;
    }

    /**
     * 设置代理服务器
     * @param string $proxy HTTP 代理通道（地址:端口）
     * @param string $auth 一个用来连接到代理的 "[username]:[password]" 格式的字符串
     * @return void
     */
    public function setCurlProxy(string $proxy, string $auth = ''): void
    {
        if ($proxy) {
            $this->setOpt(CURLOPT_PROXY, $proxy);

            if ($auth) {
                $this->setOpt(CURLOPT_PROXYUSERPWD, $auth);
            }
        }
    }

    /**
     * 自动跳转，跟随响应的Location
     * @param int $max 跟随次数
     * @return $this
     */
    public function setFollowLocation(int $max = 2): self
    {
        if (0 < $max) {
            // 自动跳转，跟随请求Location
            $this->setOpt(CURLOPT_FOLLOWLOCATION, 1);
            // 递归次数
            $this->setOpt(CURLOPT_MAXREDIRS, $max);
        }
        return $this;
    }

    /**
     * Set contents of HTTP Cookie header.
     * - 重写父类方法，修复被转码的bug
     * @param string $key   The name of the cookie
     * @param string $value The value for the provided cookie name
     * @return self
     */
    public function setCookie($key, $value): self
    {
        $this->_cookies[$key] = $value;
        $_cookies = [];
        foreach ($this->_cookies as $key => $value) {
            $_cookies[] = $key . '=' . $value;
        }
        $cookie = implode('; ', $_cookies);
        $this->setOpt(CURLOPT_COOKIE, $cookie);
        return $this;
    }

    /**
     * 批量设置cookies
     * @param string|array $cookies
     * @return $this
     */
    public function setCookies(string|array $cookies): static
    {
        if (is_string($cookies)) {
            $this->setOpt(CURLOPT_COOKIE, $cookies);
        } else {
            foreach ($cookies as $key => $value) {
                $this->setCookie($key, $value);
            }
        }
        return $this;
    }

    /**
     * 批量设置headers
     * @param array $herders
     * @return $this
     */
    public function setHeaders(array $herders): static
    {
        foreach ($herders as $key => $value) {
            $this->setHeader($key, $value);
        }
        return $this;
    }
}
