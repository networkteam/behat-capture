<?php
namespace Networkteam\Behat\Capture\Context;

use Behat\Behat\Context\BehatContext;
use Behat\Behat\Event\ScenarioEvent;
use Behat\Behat\Event\StepEvent;
use Networkteam\Behat\Capture\Exception\Exception;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

/**
 * A Behat subcontext to use FFmpeg to capture videos of (failed) scenarios
 */
class FfmpegContext extends BehatContext {

	/**
	 * @var Process
	 */
	protected $captureProcess;

	/**
	 * Only start capture for scenarios with this tag
	 *
	 * @var string
	 */
	protected $tagFilter = 'javascript';

	protected $pathToFfmpeg = 'ffmpeg';

	protected $frameRate = 24;

	protected $display = NULL;

	protected $capturePath = '/tmp';

	protected $size = '1024x768';

	protected $reportsPath = 'reports/capture';

	/**
	 * @param array $parameters
	 */
	public function __construct(array $parameters) {
		if (isset($parameters['tagFilter'])) {
			$this->tagFilter = $parameters['tagFilter'];
		}
		if (isset($parameters['pathToFfmpeg'])) {
			$this->pathToFfmpeg = $parameters['pathToFfmpeg'];
		}
		if (isset($parameters['frameRate'])) {
			$this->frameRate = $parameters['frameRate'];
		}
		if (isset($parameters['display'])) {
			$this->display = $parameters['display'];
		} else {
			$this->display = getenv('DISPLAY');
		}
		if (isset($parameters['capturePath'])) {
			$this->capturePath = $parameters['capturePath'];
		}
		if (isset($parameters['size'])) {
			$this->size = $parameters['size'];
		}
		if (isset($parameters['reportsPath'])) {
			$this->reportsPath = $parameters['reportsPath'];
		}
	}

	/**
	 * @BeforeScenario
	 * @param ScenarioEvent $event
	 */
	public function startCapture(ScenarioEvent $event) {
		if ($this->tagFilter !== NULL && !in_array($this->tagFilter, $event->getScenario()->getTags(), TRUE)) return;

		if ((string)$this->display === '') {
			throw new Exception('No display parameter given and no DISPLAY environment variable set.');
		}

		// TODO Check for existence of ffmpeg

		// We need to add "exec" before the actual command to allow the correct termination of a subprocess
		$ffmpeg = new ProcessBuilder(array(
			'exec', $this->pathToFfmpeg,
			'-y',
			'-r', $this->frameRate,
			'-f', 'x11grab',
			'-s', $this->size,
			'-i', $this->display,
			'-vc', 'x264',
			'-pix_fmt', 'yuv420p',
			$this->getTmpFilename()
		));
		$this->captureProcess = $ffmpeg->getProcess();
		$this->captureProcess->start();

		// TODO Check for errors
	}

	/**
	 * @AfterScenario
	 * @param ScenarioEvent $event
	 */
	public function stopCapture(ScenarioEvent $event) {
		if ($this->captureProcess !== NULL) {
			$tmpFilePath = $this->getTmpFilename();
			$failedCapturePath = rtrim($this->reportsPath, '/') . '/' . strtr($event->getScenario()->getTitle(), array(' ' => '_', '.' => '_')) . '.mp4';

			$this->captureProcess->stop(3, SIGTERM);

			if (file_exists($tmpFilePath)) {
				if ($event->getResult() === StepEvent::FAILED) {
					if (!is_dir(dirname($failedCapturePath))) {
						mkdir(dirname($failedCapturePath), 0777, TRUE);
					}
					if (file_exists($failedCapturePath)) {
						unlink($failedCapturePath);
					}
					rename($tmpFilePath, $failedCapturePath);
				} else {
					unlink($tmpFilePath);
				}
			}
		}
		$this->captureProcess = NULL;
	}

	/**
	 * @return string
	 */
	protected function getTmpFilename() {
		return rtrim($this->capturePath, '/') . '/.behat_ffmpeg_' . substr($this->display, 1) . '.mp4';
	}

}
