<?php

namespace Ledc\Curl;

/**
 * 自定义Curl
 */
class Curl extends \Curl\Curl
{
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
}
