<?php

namespace Qihucms\MediaProcessing;

use Illuminate\Support\Facades\Storage;

/**
 * error code:
 * 100：输入文件不能为空
 * 101：输入文件不存在
 * 102：运行失败
 * 103：发布失败
 */
class FFMpeg
{
    // 是否本地存储
    protected function isLocal()
    {
        return config('filesystems.default') == 'public';
    }

    /**
     * 格式化输入参数
     *
     * @param array|string $file 媒体文件
     * @return array|string
     */
    protected function inputFile($file)
    {
        if (empty($file)) {
            return [
                'code' => 100,
                'msg' => '输入文件不能为空',
                'data' => $file
            ];
        }

        if (is_array($file)) {
            $fileCommand = '';

            foreach ($file as $value) {
                if (Storage::exists($value)) {
                    $fileCommand .= ' -i ' . ($this->isLocal() ? public_path('storage/' . $value) : Storage::url($value));
                } else {
                    return [
                        'code' => 101,
                        'msg' => '输入文件不存在',
                        'data' => $value
                    ];
                }
            }

            return $fileCommand;
        } else {
            if (Storage::exists($file)) {
                return ' -i ' . ($this->isLocal() ? public_path('storage/' . $file) : Storage::url($file));
            } else {
                return [
                    'code' => 101,
                    'msg' => '输入文件不存在',
                    'data' => $file
                ];
            }
        }
    }

    /**
     * 格式化输出文件
     *
     * @param string $file 输出文件
     * @return string
     */
    public function outputFile($file)
    {
        if (empty($file)) {
            return $file;
        }
        // 如果存储的本地路径不存在则创建
        $outputPath = pathinfo($file, PATHINFO_DIRNAME);
        Storage::makeDirectory($outputPath);

        // 输出文件临时存放的本地路径
        return public_path('storage/' . $file);
    }

    /**
     * 执行完成发布到第三方
     *
     * @param string $path 输出路径
     * @param string $resource 资源路径
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function processed($path, $resource)
    {
        if (!$this->isLocal()) {
            $result = Storage::put($path, Storage::drive('public')->get($resource))
                ? true
                : [
                    'code' => 103,
                    'msg' => '发布失败',
                    'data' => [
                        'path' => $path,
                        'resource' => $resource
                    ]
                ];
            // 发布成功，删除本地文件
            if ($result === true) {
                Storage::drive('public')->delete($resource);
            }
            return $result;
        }

        return true;
    }

    /**
     * 处理
     *
     * @param string|array $inputFile 输入文件
     * @param string $outputFile 输出文件
     * @param string $options 参数
     * @param boolean $isSend 是否处理完成后立即发布，如果是处理过程中的一步，则不必发布到第三方
     * @param string $command 命令
     * @param int $thread 线程数
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function processing($inputFile, $outputFile, $options = ' ', $isSend = false, $command = '', $thread = 4)
    {
        // 验证输入文件，并转换为命令
        $input = $this->inputFile($inputFile);
        // 如果返回数组，则为错误信息
        if (is_array($input)) {
            return $input;
        }

        // 本地存放的路径
        $localOutputFile = $this->outputFile($outputFile);

        // 是否多线程
        $thread = $thread > 0 ? '-threads ' . $thread . ' -preset ultrafast ' : '';

        // 执行命令
        $arr_result = $this->runCommand($command . $input . $options . $thread . $localOutputFile);

        // 验证运行结果
        $processResult = $this->checkProcessResult($arr_result, $outputFile);

        if ($processResult !== true) {
            return $processResult;
        }

        return $isSend ? $this->processed($outputFile, $localOutputFile) : true;
    }

    /**
     * 运行命令
     *
     * @param string $strCommand
     * @return array
     */
    protected function runCommand(string $strCommand)
    {
        $response = [];
        $return_var = 1;
        // 执行外部命令
        exec('ffmpeg ' . $strCommand, $response, $return_var);

        return array_merge($response, ['return_var' => $return_var]);
    }

    /**
     * 验证运行结果
     *
     * @param array $result 运行结果数组
     * @param string $outputFile 本地临时文件
     * @return array|bool
     */
    protected function checkProcessResult(array $result, $outputFile)
    {
        // 执行失败，或本地未生成输出文件
        if ($result['return_var'] || !Storage::drive('public')->exists($outputFile)) {
            return [
                'code' => 102,
                'msg' => '运行失败',
                'data' => $result
            ];
        }
        return true;
    }

    /**
     * 视频转码
     * ffmpeg -i ~/Downloads/6.mp4 -vf "pad=540:952:0:'(952-ih)/2',scale=540:952" -r 25 -b:v 1000k -bufsize 1000k -maxrate 2000k -y ~/Downloads/outys1.ts
     *
     * @param string $inputFile 输入路径
     * @param string $outputFile 输出路径
     * @param int $thread 线程数
     * @param array $vfOptions 使用参数 ['pad' => ['width'=>540, 'height'=>952], 'scale' => ['width'=>540, 'height'=>952]]
     * @param int $frameRate 最大宽度
     * @param int $minRate 码率
     * @param int $maxRate 最高码率
     * @param int $bufSize 码率控制缓冲器
     * @param bool $isSend
     * @return boolean|array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avThumb(string $inputFile, $outputFile, $vfOptions = ['pad' => ['width' => 540, 'height' => 952], 'scale' => ['width' => 540, 'height' => 952]], $frameRate = 0, $minRate = 1000, $maxRate = 2000, $bufSize = 1000, $isSend = true, $thread = 4)
    {
        $options = ' ';
        if (count($vfOptions) > 0) {
            $options .= '-vf ';
            $options .= '"';
            if (array_key_exists('pad', $vfOptions)) {
                $options .= 'pad=' . $vfOptions['pad']['width'] . ':' . $vfOptions['pad']['height'];
            }
            if (array_key_exists('scale', $vfOptions)) {
                $options .= array_key_exists('pad', $vfOptions) ? ',' : '';
                $options .= 'scale=' . $vfOptions['scale']['width'] . ':' . $vfOptions['scale']['height'];
            }
            $options .= '" ';
        }
        $options .= $frameRate > 0 ? '-r ' . $frameRate . ' ' : '';
        $options .= $minRate > 0 ? '-b:v ' . $minRate . 'k ' : '';
        $options .= $bufSize > 0 ? '-bufsize ' . $bufSize . 'k ' : '';
        $options .= $maxRate > 0 ? '-maxrate ' . $maxRate . 'k ' : '';
        $options .= '-y ';

        return $this->processing($inputFile, $outputFile, $options, $isSend, '', $thread);
    }

    /**
     * 视频封面截图
     * ffmpeg -i ~/Downloads/1.mp4 -r 1 -vframes 1 -an -f mjpeg -y ~/Downloads/1.jpg
     *
     * @param string $inputFile 视频路径
     * @param string $outputFile 截图路径
     * @param string $times 截图时间
     * @param bool $isSend
     * @return boolean|array
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function vFrame(string $inputFile, $outputFile, $times = '00:00:00', $isSend = true)
    {
        return $this->processing($inputFile, $outputFile, ' -r 1 -vframes 1 -an -f mjpeg -y ', $isSend, '-ss ' . $times . ' ');
    }

    /**
     * 提取视频音频
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param bool $isSend
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avAudio(string $inputFile, $outputFile, $isSend = false)
    {
        return $this->processing($inputFile, $outputFile, ' -vcodec copy -vn -y ', $isSend);
    }

    /**
     * 提取视频静音
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param bool $isSend
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avVideo(string $inputFile, $outputFile, $isSend = false)
    {
        return $this->processing($inputFile, $outputFile, ' -vcodec copy -an -y ', $isSend);
    }

    /**
     * 读取视频信息
     *
     * @param string $file 视频路径
     * @return array
     */
    public function avInfo(string $file)
    {
        $inputFile = $this->inputFile($file);

        if (is_array($inputFile)) {
            return $inputFile;
        }

        ob_start();

        passthru(sprintf('ffmpeg %s 2>&1', $inputFile));

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
            list($width, $height) = explode('x', $matches[3]);
            $ret['width'] = (int)$width;
            $ret['height'] = (int)$height;
        }

        // Stream #0:0: Audio: cook (cook / 0x6B6F6F63), 22050 Hz, stereo, fltp, 32 kb/s
        if (preg_match("/Audio: (.*), (\d*) Hz/", $video_info, $matches)) {
            $ret['acodec'] = $matches[1];  // 音频编码
            $ret['asamplerate'] = $matches[2]; // 音频采样频率
        }

        if (isset($ret['seconds']) && isset($ret['start'])) {
            $ret['play_time'] = $ret['seconds'] + $ret['start']; // 实际播放时间
        }

        $ret['size'] = filesize(public_path('storage/' . $file)); // 视频文件大小

        return $ret;
    }

    /**
     * 设置视频尺寸
     * ffmpeg -i ~/Downloads/10.mp4 -vf "scale=iw*min(540/iw\,960/ih):ih*min(540/iw\,960/ih),pad=540:960:(540-iw)/2:(960-ih)/2" -y ~/Downloads/output_xx.mp4
     *
     * @param $inputFile
     * @param $outputFile
     * @param int $minWidth
     * @param int $minHeight
     * @param bool $isSend
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avSize($inputFile, $outputFile, $minWidth = 540, $minHeight = 960, $isSend = false)
    {
        return $this->processing($inputFile, $outputFile, ' -vf "scale=iw*min(' . $minWidth . '/iw\,' . $minHeight . '/ih):ih*min(' . $minWidth . '/iw\,' . $minHeight . '/ih),pad=' . $minWidth . ':' . $minHeight . ':(' . $minWidth . '-iw)/2:(' . $minHeight . '-ih)/2" -y ', $isSend);
    }

    /**
     * 合拍：双画面展示
     * ffmpeg -i ~/Downloads/10.mp4 -i ~/Downloads/5.mp4 -filter_complex "[1:v]pad=iw*2:ih[a];[a][0:v]overlay=w" -y ~/Downloads/output_2v.mp4
     *
     * @param array $inputFile [源视频, 主视频]
     * @param string $outputFile
     * @param bool $isSend
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avSplicing(array $inputFile, $outputFile, $isSend = false)
    {
        // 源视频
        $video_a_info = $this->avInfo($inputFile[0]);
        // 主视频
        $video_b_info = $this->avInfo($inputFile[1]);
        // 如果合成两个视频源尺寸不同，更新第二个视频尺寸为第二视频的尺寸
        if ($video_a_info['width'] != $video_b_info['width'] || $video_a_info['height'] != $video_b_info['height']) {
            Storage::makeDirectory('tmp_video');
            $tmpFile = 'tmp_video/' . md5($outputFile) . '.mp4';
            $result = $this->avSize($inputFile[1], $tmpFile, $video_a_info['width'], $video_a_info['height'], false);
            if ($result === true) {
                $inputFile[1] = $tmpFile;
            } else {
                return $result;
            }
        }
        // 以左侧视频时长截取视频
        if (isset($video_b_info['seconds']) && $video_b_info['seconds'] > 0) {
            $times = ' -t ' . $video_b_info['seconds'];
        } else {
            $times = '';
        }
        return $this->processing($inputFile, $outputFile, ' -filter_complex "[1:v]pad=iw*2:ih[a];[a][0:v]overlay=w"' . $times . ' -y ', $isSend);
    }

    /**
     * 拍同款：提取源视频音频合成到新的视频上，按源视频限制时长
     *
     * @param array $inputFile [新视频, 源视频]
     * @param $outputFile
     * @param bool $isMute
     * @param bool $isSend
     * @param int $thread
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avSameStyle(array $inputFile, $outputFile, $isMute = true, $isSend = true, $thread = 4)
    {
        // 源视频信息
        $arr_input_source_info = $this->avInfo($inputFile[1]);

        // 提取源视频音频文件
        Storage::makeDirectory('tmp_audio');
        $audioFile = 'tmp_audio/' . md5($outputFile) . '.mp3';
        $adResult = $this->avAudio($inputFile[1], $audioFile, false);
        if ($adResult === true) {
            if ($isMute) {
                Storage::makeDirectory('tmp_video');
                $videoFile = 'tmp_video/' . md5($outputFile) . '.mp4';
                $avResult = $this->avVideo($inputFile[0], $videoFile, false);
                if ($avResult == true) {
                    return $this->processing([$audioFile, $videoFile], $outputFile, ' -c:v copy -t ' . $arr_input_source_info['seconds'] . ' -y ', $isSend, '', $thread);
                } else {
                    return $avResult;
                }
            } else {
                $videoFile = $inputFile[0];
            }
            // 以新视频时长为准合成音视频
            return $this->processing([$videoFile, $audioFile], $outputFile, ' -c:v copy -map 0:v:0 -filter_complex "[0:a][1:a]amerge=inputs=2[aout]" -map "[aout]" -ac 2 -t ' . $arr_input_source_info['seconds'] . ' -y ', $isSend, '', $thread);
        } else {
            // 提取失败
            return $adResult;
        }
    }

    /**
     * 短音频循环输入到视频中
     * ffmpeg -i ~/Downloads/1.mp4 -stream_loop -1 -i ~/Downloads/short_mp3.mp3 -shortest -threads 4 -preset ultrafast ~/Downloads/output1.mp4
     *
     * @param array $inputFile [mp4, mp3]
     * @param $outputFile
     * @param bool $isSend
     * @param int $thread
     * @return array|bool|string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avBgAudioLoop(array $inputFile, $outputFile, $isSend = true, $thread = 4)
    {
        $input = $this->inputFile($inputFile);
        if (is_array($input)) {
            return $input;
        }

        // 本地存放的路径
        $localOutputFile = $this->outputFile($outputFile);

        // 视频静音
        Storage::makeDirectory('tmp_video');
        $videoFile = 'tmp_video/' . md5($outputFile) . '.mp4';
        $avResult = $this->avVideo($inputFile[0], $videoFile, false);
        if ($avResult == true) {
            $command = ' -i ' . public_path('storage/' . $videoFile) . ' -stream_loop -1';
            $command .= ' -i ' . public_path('storage/' . $inputFile[1]) . ' -shortest';
            $command .= $thread > 0 ? ' -threads ' . $thread . ' -preset ultrafast ' : '';
            $command .= ' -y ';
            $command .= $localOutputFile;

            $result = $this->runCommand($command);

            // 验证运行结果
            $processResult = $this->checkProcessResult($result, $outputFile);

            if ($processResult !== true) {
                return $processResult;
            }

            return $isSend ? $this->processed($outputFile, $localOutputFile) : true;
        } else {
            return $avResult;
        }
    }

    /**
     * 添加背景音乐
     * ffmpeg -i ~/Downloads/mp3.mp3 -i ~/Downloads/1.mp4 -ss 0 -t 10 -y ~/Downloads/out.mp4
     *
     * @param array $inputFile [0 => '音频', 1 => '视频']
     * @param $outputFile
     * @param $isSend
     * @param int $thread
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avBgAudio(array $inputFile, $outputFile, $isSend = true, $thread = 4)
    {
        $av_info = $this->avInfo($inputFile[1]);
        // 视频静音
        Storage::makeDirectory('tmp_video');
        $videoFile = 'tmp_video/' . md5($outputFile) . '.mp4';
        $avResult = $this->avVideo($inputFile[1], $videoFile, false);
        if ($avResult == true) {
            $inputFile[1] = $videoFile;
            return $this->processing($inputFile, $outputFile, ' -t ' . $av_info['seconds'] . ' -y ', $isSend, '', $thread);
        } else {
            return $avResult;
        }
    }

    /**
     * 在指定时间点添加单个音频
     * ffmpeg -i ~/Downloads/output.mp4 -i ~/Downloads/short_mp3.mp3 -filter_complex "[1]adelay=10000|10000[s2]" -map 0:v -map "[s2]" -c:v copy ~/Downloads/output2.mp4
     *
     * @param array $inputFile [视频, 音频]
     * @param string $outputFile 输出目录
     * @param int $times 插入时间
     * @param bool $sourceMute 是否清除源视频音频
     * @param bool $isSend 是否发布
     * @param int $thread 线程数
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avBgAudioForTimes(array $inputFile, $outputFile, $times = 0, $sourceMute = true, $isSend = true, $thread = 4)
    {
        $videoFile = $inputFile[0];
        // 如果需要清除源视频音频
        if ($sourceMute) {
            Storage::makeDirectory('tmp_video');
            $videoFile = 'tmp_video/' . md5($outputFile) . '.mp4';
            // 导出静音视频
            $result = $this->avVideo($inputFile[0], $videoFile, false);
            if ($result !== true) {
                return $result;
            }
        }
        return $this->processing([$videoFile, $inputFile[1]], $outputFile, ' -filter_complex "[0:a]aformat=sample_fmts=fltp:channel_layouts=stereo,volume=1[a1];[1:a]aformat=sample_fmts=fltp:channel_layouts=stereo,volume=1,adelay=' . $times . '|' . $times . '|' . $times . '[a2];[a1][a2]amix=inputs=2:duration=first[aout]" -map "[aout]" -ac 2 -c:v copy -map 0:v:0 -y ', $isSend, '', $thread);
    }

    /**
     *  多视频合成，添加片头片尾
     * ffmpeg -f concat -i ~/Downloads/files.txt -c:v libx264 -c:a copy -y ~/Downloads/output_more.mp4
     *
     * @param array $inputFile
     * @param $outputFile
     * @param array $vfOption
     * @param int $frameRate
     * @param int $minRate
     * @param int $maxRate
     * @param int $bufSize
     * @param bool $isSend
     * @param int $thread
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avConcat(array $inputFile, $outputFile, $vfOption = ['pad' => ['width' => 540, 'height' => 952], 'scale' => ['width' => 540, 'height' => 952]], $frameRate = 0, $minRate = 1000, $maxRate = 2000, $bufSize = 1000, $isSend = true, $thread = 4)
    {
        // 转换视频文件为TS格式，并写入到合成列表中
        $str = '';
        Storage::makeDirectory('tmp_video');
        foreach ($inputFile as $file) {
            $filename = pathinfo($file, 'PATHINFO_FILENAME') . '.ts';
            $result = $this->avThumb($file, 'tmp_video/' . $filename, $vfOption, $frameRate, $minRate, $maxRate, $bufSize, false, $thread);
            if ($result !== true) {
                return $result;
            }
            $str .= 'file ' . $filename . "\n";
        }
        $files = 'tmp_video/files.txt';
        Storage::drive('public')->put($files, $str);

        return $this->processing($files, $outputFile, ' -c:v libx264 -c:a copy -y ', $isSend, ' -f concat ', $thread);
    }

    /**
     * 生成视频Gif
     * ffmpeg -i ~/Downloads/1.mp4 -vframes 30 -f gif -y ~/Downloads/output_gif.gif
     *
     * @param $inputFile
     * @param $outputFile
     * @param int $count 视频前$count帧
     * @param bool $isSend
     * @return array|bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function avGif($inputFile, $outputFile, $count = 30, $isSend = true)
    {
        return $this->processing($inputFile, $outputFile, ' -vframes ' . $count . ' -f gif ', $isSend);
    }
}