# qihucms-ffmpeg

$a = $FFMpeg->avGif('video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/6.gif', 30, false);

$a = $FFMpeg->avBgAudioForTimes(['video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/short_mp3.mp3'], 'test/6.mp4', 5000, false);

$a = $FFMpeg->avBgAudioForTimes(['video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/short_mp3.mp3'], 'test/6.mp4', 5000, false);

$a = $FFMpeg->avBgAudio(['test/short_mp3.mp3', 'video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4'], 'test/4.mp4', false);

$a = $FFMpeg->avBgAudioLoop(['video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/short_mp3.mp3'], 'test/5.mp4', false);

$a = $FFMpeg->avSameStyle(['video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'video/short/b54351dbee8fee1a9d217c14e0dd6f96.mp4'], 'test/4.mp4', false);

$a = $FFMpeg->avSplicing(['video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'video/short/b54351dbee8fee1a9d217c14e0dd6f96.mp4'], 'test/4.mp4');

$a = $FFMpeg->avSize('video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/3.mp4', 400, 350);

$a = $FFMpeg->avVideo('video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/2.mp4');

$a = $FFMpeg->avAudio('video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/1.mp3');

$a = $FFMpeg->vFrame('video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/1.jpg');

$a = $FFMpeg->avThumb('video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4', 'test/1.mp4');

$a = $FFMpeg->avInfo('video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4');

$a = Storage::put('/test/a.mp4', Storage::drive('public')->get('video/short/6b4adda4ace93ebf9cedb5cb3e5f0c31.mp4'));//$FFMpeg->avConcat();

dd($a);