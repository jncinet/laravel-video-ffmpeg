<?php

namespace Qihucms\VideoFFMpeg;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FFMpeg
{
    protected $globalParameter = '';
    protected $inputParameter = [];
    protected $inputFile = [];
    protected $outputParameter = [];
    protected $outputFile = [];
    protected $width;
    protected $height;
    // 输入文件时长限制
    protected $inputDuration;
    /**
     * 压缩视频（输出视频参数）
     * -preset 输出的视频质量，会影响文件的生成速度，有以下几个可用的值：
     * ultrafast, superfast, veryfast, faster, fast, medium, slow, slower, veryslow
     * @var string
     */
    protected $compress = ' -c:v libx264 -b:v 1500k -preset superfast';

    /**
     * FFMpeg constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->width = Cache::has('ffmpeg_video_width') ? Cache::get('ffmpeg_video_width', 544) : 544;
        $this->height = Cache::has('ffmpeg_video_height') ? Cache::get('ffmpeg_video_height', 960) : 960;
        $this->inputDuration = Cache::has('ffmpeg_input_duration') ? Cache::get('ffmpeg_input_duration', 0) : 0;
    }

    /**
     * 转码压缩
     *
     * @param $video
     * @param $saveName
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avThumb($video, $saveName)
    {
        $that = $this->setInput($video);
        if ($this->inputDuration > 0) {
            $that = $that->setInputParameter('-t ' . $this->inputDuration);
        }
        return $that->setOutputParameter('-vf "scale=' . $this->width . ':ih,crop=' . $this->width . ':\'min(' . $this->height . ', ih)\',pad=' . $this->width . ':' . $this->height . ':0:-1"' . $this->compress)
            ->setOutput($saveName)
            ->thread(4)
            ->overwrite()
            ->runCommand();
    }

    /**
     * 指定时间截取一张图片
     *
     * @param string $video 源视频
     * @param string $saveName 输出图片文件
     * @param string $times 截图时间
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function vFrame(string $video, string $saveName, $times = '00:00:00')
    {
        return $this->setInput($video)
            ->setInputParameter('-ss ' . $times)
            ->setOutputParameter('-r 1 -vframes 1 -an -q:v 3 -f mjpeg')
            ->setOutput($saveName)
            ->thread(4)
            ->overwrite()
            ->runCommand();
    }

    /**
     * 执行ffmpeg命令
     *
     * @param null $command
     * @return array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function runCommand($command = null)
    {
        $response = [];
        $return_var = 1;

        if (is_null($command)) {
            // 输入文件及参数
            $inputFileCount = count($this->inputFile);
            $inputParameterCount = count($this->inputParameter);
            $strInputCommand = '';

            foreach ($this->inputFile as $k => $file) {
                if (!$this->checkUrl($file)) {
                    if (config('filesystems.default') == 'public' || substr($file, -3) === 'txt') {
                        $file = storage_path('app/public/' . $file);
                    } else {
                        $file = Storage::url($file);
                    }
                }
                $strInputCommand .= ' ';
                // 如果输入文件数和参数数量相等则附加参数
                $strInputCommand .= $inputFileCount == $inputParameterCount
                    ? $this->inputParameter[$k] . ' -i ' . $file
                    : '-i ' . $file;
            }

            // 输出文件及参数
            $outputFileCount = count($this->outputFile);
            $outputParameterCount = count($this->outputParameter);
            $strOutputCommand = '';

            foreach ($this->outputFile as $k => $file) {
                $file = storage_path('app/public/' . $file);
                $strOutputCommand .= ' ';
                // 如果输入文件数和参数数量相等则附加参数
                $strOutputCommand .= $outputFileCount == $outputParameterCount
                    ? $this->outputParameter[$k] . ' ' . $file
                    : $file;
            }

            $command = $this->globalParameter . $strInputCommand . $strOutputCommand;
        }
        // 执行外部命令
        exec('ffmpeg' . $command . ' 2>&1', $response, $return_var);

        $result = array_merge($response, ['return_var' => $return_var]);

        if ($return_var == 0) {
            $this->processed();
        }

        // 执行完成后，清除设置
        $this->globalParameter = '';
        $this->inputParameter = [];
        $this->inputFile = [];
        $this->outputParameter = [];
        $this->outputFile = [];
        $this->inputDuration = 0;

        return $result;
    }

    /**
     * 文件生成后发布到第三方存储
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function processed()
    {
        if (config('filesystems.default') != 'public') {
            foreach ($this->outputFile as $file) {
                if (Storage::put($file, Storage::disk('public')->get($file))) {
                    Storage::disk('public')->delete($file);
                }
            }
        }
    }

    /**
     * 多线程处理
     *
     * @param int $count
     * @return $this
     */
    public function thread($count = 2)
    {
        $this->globalParameter .= ' -threads ' . $count;
        return $this;
    }

    /**
     * 如果输出存在是否覆盖文件
     *
     * @return $this
     */
    public function overwrite()
    {
        $this->globalParameter .= ' -y';
        return $this;
    }

    /**
     * 输入文件
     *
     * @param array|string $files
     * @return $this
     */
    public function setInput($files)
    {
        $files = is_array($files) ? $files : [$files];
        $this->inputFile = array_merge($this->inputFile, $files);
        return $this;
    }

    /**
     * 输入文件参数
     *
     * @param array|string $parameter
     * @return $this
     */
    public function setInputParameter($parameter)
    {
        $parameter = is_array($parameter) ? $parameter : [$parameter];
        $this->inputParameter = array_merge($this->inputParameter, $parameter);
        return $this;
    }

    /**
     * 输出文件
     *
     * @param array|string $files
     * @return $this
     */
    public function setOutput($files)
    {
        $files = is_array($files) ? $files : [$files];
        $this->outputFile = array_merge($this->outputFile, $files);
        return $this;
    }

    /**
     * 输出文件参数
     *
     * @param array|string $parameter
     * @return $this
     */
    public function setOutputParameter($parameter)
    {
        $parameter = is_array($parameter) ? $parameter : [$parameter];
        $this->outputParameter = array_merge($this->outputParameter, $parameter);
        return $this;
    }

    /**
     * @param int $seconds
     */
    public function setInputDuration(int $seconds)
    {
        $this->inputDuration = $seconds;
    }

    /**
     * @param int $width
     */
    public function setWidth(int $width)
    {
        $this->width = $width;
    }

    /**
     * @param int $height
     */
    public function setHeight(int $height)
    {
        $this->height = $height;
    }

    /**
     * 读取视频信息
     *
     * @param string $file 视频路径
     * @return array
     */
    public function avInfo($file)
    {
        if (!$this->checkUrl($file)) {
            if (config('filesystems.default') == 'public') {
                $file = storage_path('app/public/' . $file);
            } else {
                $file = Storage::url($file);
            }
        }

        ob_start();

        passthru(sprintf('ffmpeg -i %s 2>&1', $file));

        $video_info = ob_get_contents();

        ob_end_clean();

        // 使用输出缓冲，获取ffmpeg所有输出内容
        $ret = [];

        // Duration: 00:33:42.64, start: 0.000000, bitrate: 152 kb/s
        if (preg_match("/Duration: (.*?), start: (.*?), bitrate: (\d*) kb\/s/", $video_info, $matches)) {
            $ret['duration'] = $matches[1]; // 视频长度
            $duration = explode(':', $matches[1]);
            $ret['seconds'] = $duration[0] * 3600 + $duration[1] * 60 + $duration[2]; // 转为秒数
            $ret['start'] = $matches[2]; // 开始时间
            $ret['bitrate'] = $matches[3]; // bitrate 码率 单位kb
        }

        // Stream #0:1: Video: rv20 (RV20 / 0x30325652), yuv420p, 352x288, 117 kb/s, 15 fps, 15 tbr, 1k tbn, 1k tbc
        if (preg_match("/Video: (.*?), (.*?), (.*?)[,\s]/", $video_info, $matches)) {
            $ret['vcodec'] = $matches[1];  // 编码格式
            $ret['vformat'] = $matches[2]; // 视频格式
            $ret['resolution'] = $matches[3]; // 分辨率

            $ret['width'] = 0;
            $ret['height'] = 0;

            $w_h = explode('x', $matches[3]);
            if (count($w_h) == 2) {
                $ret['width'] = (int)$w_h[0];
                $ret['height'] = (int)$w_h[1];
            }
        }

        // Stream #0:0: Audio: cook (cook / 0x6B6F6F63), 22050 Hz, stereo, fltp, 32 kb/s
        if (preg_match("/Audio: (.*), (\d*) Hz/", $video_info, $matches)) {
            $ret['acodec'] = $matches[1];  // 音频编码
            $ret['asamplerate'] = $matches[2]; // 音频采样频率
        }

        if (isset($ret['seconds']) && isset($ret['start'])) {
            $ret['play_time'] = $ret['seconds'] + $ret['start']; // 实际播放时间
        }

        return $ret;
    }

    /**
     * 验证是否网址
     *
     * @param string $url 网址
     * @return bool
     */
    public function checkUrl($url)
    {
        $pattern = "/^(http|https):\/\/.*$/i";
        if (preg_match($pattern, $url)) {
            return true;
        } else {
            return false;
        }
    }
}