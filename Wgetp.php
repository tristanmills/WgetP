<?php

class FileSystem {

	/**
	 * Deletes filename and any contents. A E_USER_WARNING level error will be generated on failure.
	 *
	 * @param string $filename Path to the file or directory.
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	public static function rmtree($filename) {
		if (file_exists($filename) === false) {
			trigger_error('rmtree(' . $filename . '): No such file or directory', E_USER_WARNING);
			return false;
		}
		if (is_dir($filename)) {
			$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filename, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($objects as $object) {
				if ($object->isDir()) {
					rmdir($object->getPathname());
				} else {
					unlink($object->getPathname());
				}
			}
			return rmdir($filename);
		} elseif (is_file($filename)) {
			return unlink($filename);
		} else {
			trigger_error('rmtree(' . $filename . '): Invalid argument', E_USER_WARNING);
			return false;
		}
	}

	/**
	 * Tells whether the given dirname is an empty directory.
	 *
	 * @param string $dirname Path to the directory.
	 * @return bool Returns TRUE on success or FALSE on failure.
	 */
	public static function is_dir_empty($dirname) {
		if (is_dir($dirname)) {
			$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirname, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
			if (iterator_count($objects) === 0) {
				return true;
			} else {
				return false;
			}
		} else {
			trigger_error('is_dir_empty(' . $dirname . '): No such directory', E_USER_WARNING);
			return false;
		}
	}

}

class Http {


	/**
	 * Tells whether the given URL is relative.
	 *
	 * @param string $path The path to be checked.
	 * @return mixed Returns site-relative, absolute, document-relative, or FALSE on failure.
	 */
	public static function path_type($path) {

		$path_parts = parse_url($path);

		if (isset($path_parts['host'])) {
			return 'absolute';
		} elseif (stripos($path, '/') === true) {
			return 'site-relative';
		}  elseif (stripos($path, '../') === true || stripos($path, './') === true) {
			return 'document-relative';
		} else {
			trigger_error('path_type(' . $path . '): Unknown path type', E_USER_WARNING);
			return false;
		}

	}

	/**
	 * Tells whether the given URL is relative.
	 *
	 * @param string $url The URL to be checked.
	 * @return bool Returns TRUE if relative, or FALSE.
	 */
	public static function is_relative_url($url) {
		if (stripos($url, 'http://') === false && stripos($url, 'https://') === false && stripos($url, '//') === false) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Tells whether the given string is encoded data.
	 *
	 * @param string $string The string to be checked.
	 * @return bool Returns TRUE if encoded data, or FALSE.
	 */
	public static function is_encoded_data($string) {
		if (stripos($string, 'data:') !== false) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * .
	 *
	 * @param string $resourceUrl Relative path to the file.
	 */
	public static function build_url($url, $resourceUrl) {
		$parsed_url = array_merge(parse_url($url), array('path' => $resourceUrl));
		$scheme		= isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
		$host		= isset($parsed_url['host']) ? $parsed_url['host'] : '';
		$port		= isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
		$user		= isset($parsed_url['user']) ? $parsed_url['user'] : '';
		$pass		= isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
		$pass		= ($user || $pass) ? $pass. '@' : '';
		$path		= isset($parsed_url['path']) ? $parsed_url['path'] : '';
		$query		= isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
		$fragment	= isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
		return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
	}

}

class Wgetp {

	// TODO: Refactor to generic FileSystem method.
	/**
	 * Moves matching files from the tmp directory into the corresponding sub directory.
	 *
	 * @param string $path Path to the file.
	 * @param string $subdir Path to the relevant sub directory.
	 */
	private function moveRelatedFile($path, $subdir) {
		$decoded_path = rawurldecode($path);
		$decoded_path = html_entity_decode($decoded_path);
		$possible_filenames = array();
		$possible_filenames[] = $decoded_path;
		$possible_filenames[] = substr($decoded_path, 0, strpos($decoded_path, '#'));
		$possible_filenames[] = substr($decoded_path, 0, strpos($decoded_path, '?'));
		foreach ($possible_filenames as $filename) {
			$old_file_path = $this->settings['location'] . '/' . $this->settings['tmpDirectory'] . '/' . $filename;
			$new_file_path = $this->settings['location'] . '/' . $subdir. '/' . $filename;
			if (is_file($old_file_path)) {
				rename($old_file_path, $new_file_path);
				return $new_file_path;
			}
		}
	}


	/**
	 * @var object
	 */
	private $DOMDocument;

	/**
	 * @var object
	 */
	private $DOMXpath;

	/**
	 * @var array
	 */
	private $settings;

	/**
	 * @var string
	 */
	private $tmpHtmlFilename;

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var string
	 */
	private $user;

	/**
	 * @var string
	 */
	private $pass;

	/**
	 * __construct()
	 *
	 * @param array $settings Description of settings.
	 *
	 */
	function __construct($settings = array()) {

		$this->settings = array_merge(array(
			'location'        => './files',
			'tmpDirectory'    => 'tmp',
			'jsDirectory'     => 'js',
			'cssDirectory'    => 'css',
			'fontsDirectory'  => 'fonts',
			'flashDirectory'  => 'flash',
			'imgDirectory'    => 'img',
			'wgetPath'        => '/usr/bin/wget',
			'userAgent'       => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.76 Safari/537.36',
			'excludedDomains' => array('fonts.googleapis.com', 'maps.googleapis.com', 'fast.fonts.com' ,'use.typekit.net'),
			'htmlFilename'    => 'index.html',
		), $settings);

		$this->settings['location'] = rtrim($this->settings['location'], '/');

	}

	/**
	 * Prepare the directory structure.
	 *
	 */
	private function prepare_directories() {

		if (file_exists($this->settings['location'])) {

			FileSystem::rmtree($this->settings['location']);

		}

		mkdir($this->settings['location']);
		mkdir($this->settings['location'] . '/' . $this->settings['jsDirectory']);
		mkdir($this->settings['location'] . '/' . $this->settings['cssDirectory']);
		mkdir($this->settings['location'] . '/' . $this->settings['fontsDirectory']);
		mkdir($this->settings['location'] . '/' . $this->settings['flashDirectory']);
		mkdir($this->settings['location'] . '/' . $this->settings['imgDirectory']);

	}

	/**
	 * Set the tmp HTML filename.
	 *
	 */
	private function setTmpHtmlFilename() {

		$options = array(
			'--execute robots=off',
			'--quiet',
			'--no-directories',
			'--directory-prefix=' . $this->settings['location'] . '/' . $this->settings['tmpDirectory'],
			'--default-page=' . $this->settings['htmlFilename'],
			'--adjust-extension',
			'--no-cache',
			'--user-agent="' . $this->settings['userAgent'] . '"',
			'--no-check-certificate',
			'--user=' . $this->user,
			'--password=' . $this->pass,
		);

		$command = $this->settings['wgetPath'] . ' ' . implode(' ', $options) . ' ' . escapeshellarg($this->url);

		shell_exec($command);

		$files = new FilesystemIterator($this->settings['location'] . '/' . $this->settings['tmpDirectory']);
		$fileCount = iterator_count($files);

		foreach ($files as $file) {

			if ($fileCount === 1 && ($file->getExtension() === 'html' || $file->getExtension() === 'htm' || $file->getExtension() === 'shtml')) {

				$this->tmpHtmlFilename = $file->getFilename();
				break;

			} else {

				trigger_error('setTmpHtmlFilename(): ', E_USER_WARNING);
				break;

			}
		}

		FileSystem::rmtree($this->settings['location'] . '/' . $this->settings['tmpDirectory']);

	}

	/**
	 * .
	 *
	 */
	private function download_files() {

		$options = array(
			'--execute robots=off',
			'--quiet',
			'--tries=2',
			'--no-directories',
			'--directory-prefix=' . $this->settings['location'] . '/' . $this->settings['tmpDirectory'],
			'--default-page=' . $this->settings['htmlFilename'],
			'--adjust-extension',
			'--no-cache',
			'--user-agent="' . $this->settings['userAgent'] . '"',
			'--no-check-certificate',
			'--convert-links',
			'--page-requisites',
			'--exclude-domains=' . implode(',', $this->settings['excludedDomains']),
			'--ignore-tags=iframe,embed',
			'--span-hosts',
			'--user=' . $this->user,
			'--password=' . $this->pass,
		);

		$command = $this->settings['wgetPath'] . ' ' . implode(' ', $options) . ' ' . escapeshellarg($this->url);

		shell_exec($command);

	}

	/**
	 * .
	 *
	 */
	private function processHtml() {

		$oldHtmlFilePath = $this->settings['location'] . '/' . $this->settings['tmpDirectory'] . '/' . $this->tmpHtmlFilename;
		$newHtmlFilePath = $this->settings['location'] . '/' . $this->settings['htmlFilename'];

		rename($oldHtmlFilePath, $newHtmlFilePath);

		$htmlContent = file_get_contents($this->settings['location'] . '/' . $this->settings['htmlFilename']);
		$htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', mb_detect_encoding($htmlContent));
		$htmlContent = mb_convert_encoding($htmlContent, 'html-entities', 'UTF-8');

		$this->DOMDocument = new DOMDocument();
		@$this->DOMDocument->loadHTML($htmlContent);
		$this->DOMXpath = new DOMXpath($this->DOMDocument);

	}

	/**
	 * Convert style blocks into normal CSS files.
	 *
	 */
	private function convertStyleBlocks() {

		$styleBlockNodes = $this->DOMXpath->query('//style');

		foreach ($styleBlockNodes as $index => $styleBlockNode) {

			$cssFilename = 'style-block-' . sprintf('%02d', $index + 1) . '.css';
			$cssFilePath = $this->settings['location'] . '/' . $this->settings['tmpDirectory'] . '/' . $cssFilename;
			$cssContent = $styleBlockNode->nodeValue;

			file_put_contents($cssFilePath, $cssContent);

			$link = $this->DOMDocument->createElement('link');
			$link->setAttribute('rel', 'stylesheet');
			$link->setAttribute('media', $styleBlockNode->getAttribute('media'));
			$link->setAttribute('href', $cssFilename);

			$styleBlockNode->parentNode->insertBefore($link, $styleBlockNode);
			$styleBlockNode->parentNode->removeChild($styleBlockNode);

		}

	}

	/**
	 * Convert script blocks into normal Javascript files.
	 *
	 */
	private function convertScriptBlocks() {

		$scriptBlockNodes = $this->DOMXpath->query('//script[not(@src)]');

		foreach ($scriptBlockNodes as $index => $scriptBlockNode) {

			$jsFilename = 'script-block-' . sprintf('%02d', $index + 1) . '.js';
			$jsFilePath = $this->settings['location'] . '/' . $this->settings['tmpDirectory'] . '/' . $jsFilename;
			$jsContent = $scriptBlockNode->nodeValue;

			file_put_contents($jsFilePath, $jsContent);

			$script = $this->DOMDocument->createElement('script');
			$script->setAttribute('type', 'text/javascript');
			$script->setAttribute('src', $jsFilename);

			$scriptBlockNode->parentNode->insertBefore($script, $scriptBlockNode);
			$scriptBlockNode->parentNode->removeChild($scriptBlockNode);

		}

	}

	/**
	 * .
	 *
	 */
	private function processComments() {

		$commentNodes = $this->DOMXpath->query('//comment()');

		foreach ($commentNodes as $commentNode) {

			$newCommentContent = $this->processCommentContent($commentNode->nodeValue);
			$commentNode->nodeValue = $newCommentContent;

		}

	}

	/**
	 * Find URLs in the comments, download the files, then update the comments.
	 *
	 * @param string $commentContent The content of a comment.
	 * @return string Returns new comment content.
	 */
	private function processCommentContent($commentContent) {

		$patterns = array();
		$replacements = array();

		preg_match_all('~link.*?href\=[\'\"](.*?)[\'\"]~', $commentContent, $cssMatches);
		preg_match_all('~script.*?src\=[\'\"](.*?)[\'\"]~', $commentContent, $jsMatches);
		preg_match_all('~img.*?src\=[\'\"](.*?)[\'\"]~', $commentContent, $imgMatches);

		if (isset($cssMatches[1])) {

			foreach ($cssMatches[1] as $oldCssHref) {

				if (empty($oldCssHref) === false && Http::is_relative_url($oldCssHref)) {

					$newCssHref = './' . $this->settings['cssDirectory'] . '/' . basename($oldCssHref);
					$filePath = $this->settings['location'] . '/' . $this->settings['cssDirectory'] . '/' . basename($oldCssHref);
					$sourceUrl = Http::build_url($this->url, $oldCssHref);

					$patterns[] = $oldCssHref;

					$content = @file_get_contents($sourceUrl);

					if ($content) {

						file_put_contents($filePath, $content);

						$replacements[] = $newCssHref;

					} else {

						$replacements[] = $sourceUrl;

					}

				}

			}

		}

		if (isset($jsMatches[1])) {

			foreach ($jsMatches[1] as $oldJsSrc) {

				if (empty($oldJsSrc) === false && Http::is_relative_url($oldJsSrc)) {

					$newJsSrc = './' . $this->settings['jsDirectory'] . '/' . basename($oldJsSrc);
					$filePath = $this->settings['location'] . '/' . $this->settings['jsDirectory'] . '/' . basename($oldJsSrc);
					$sourceUrl = Http::build_url($this->url, $oldJsSrc);

					$patterns[] = $oldJsSrc;

					$content = @file_get_contents($sourceUrl);

					if ($content) {

						file_put_contents($filePath, $content);

						$replacements[] = $newJsSrc;

					} else {

						$replacements[] = $sourceUrl;

					}

				}

			}

		}

		if (isset($imgMatches[1])) {

			foreach ($imgMatches[1] as $oldImgSrc) {

				if (empty($oldImgSrc) === false && Http::is_relative_url($oldImgSrc)) {

					$newImgSrc = './' . $this->settings['imgDirectory'] . '/' . basename($oldImgSrc);
					$filePath = $this->settings['location'] . '/' . $this->settings['imgDirectory'] . '/' . basename($oldImgSrc);
					$sourceUrl = Http::build_url($this->url, $oldImgSrc);

					$patterns[] = $oldImgSrc;

					$content = @file_get_contents($sourceUrl);

					if ($content) {

						file_put_contents($filePath, $content);

						$replacements[] = $newImgSrc;

					} else {

						$replacements[] = $sourceUrl;

					}

				}

			}

		}

		$newCommentContent = str_replace($patterns, $replacements, $commentContent);

		return $newCommentContent;

	}

	/**
	 * .
	 *
	 */
	private function processImages() {

		// For XPath 2.0
		// $imageNodes = $this->DOMXpath->query('//img | //input[lower-case(@type)="image"]');
		$imageNodes = $this->DOMXpath->query('//img | //input[@type="image"] | //input[@type="Image"] | //input[@type="IMAGE"]');

		foreach ($imageNodes as $imageNode) {

			$oldImageSrc = $imageNode->getAttribute('src');

			if (empty($oldImageSrc) === false && Http::is_relative_url($oldImageSrc)) {

				$newImageSrc = './' . $this->settings['imgDirectory'] . '/' . $oldImageSrc;

				$this->moveRelatedFile($oldImageSrc, $this->settings['imgDirectory']);

				$imageNode->setAttribute('src', $newImageSrc);

			}
		}
	}

	/**
	 * .
	 *
	 */
	private function processFlash() {

		$flashNodes = $this->DOMXpath->query('//object[@data]');

		foreach ($flashNodes as $flashNode) {

			$oldFlashData = $flashNode->getAttribute('data');

			if (empty($oldFlashData) === false && Http::is_relative_url($oldFlashData)) {

				$newFlashData = './' . $this->settings['flashDirectory'] . '/' . $oldFlashData;

				$this->moveRelatedFile($oldFlashData, $this->settings['flashDirectory']);

				$flashNode->setAttribute('data', $newFlashData);

			}

		}

	}

	/**
	 * .
	 *
	 */
	private function processFavicon() {

		// For XPath 2.0
		// $faviconNodes = $this->DOMXpath->query('//link[contains(lower-case(@rel), "icon")]');
		$faviconNodes = $this->DOMXpath->query('//link[contains(@rel, "icon")] | //link[contains(@rel, "Icon")] | //link[contains(@rel, "ICON")]');

		foreach ($faviconNodes as $faviconNode) {

			$oldfaviconHref = $faviconNode->getAttribute('href');

			if (empty($oldfaviconHref) === false && Http::is_relative_url($oldfaviconHref)) {

				$newfaviconHref = './' . $this->settings['imgDirectory'] . '/' . $oldfaviconHref;

				$this->moveRelatedFile($oldfaviconHref, $this->settings['imgDirectory']);

				$faviconNode->setAttribute('href', $newfaviconHref);

			}

		}

		$oldFaviconFilePath = $this->settings['location'] . '/' . $this->settings['tmpDirectory'] . '/favicon.ico';
		$newFaviconFilePath = $this->settings['location'] . '/favicon.ico';

		if (file_exists($oldFaviconFilePath)) {

			rename($oldFaviconFilePath, $newFaviconFilePath);

		}

	}

	/**
	 * .
	 *
	 */
	private function processInlineCss() {

		$inlineCssNodes = $this->DOMXpath->query('//*[@style]');

		foreach ($inlineCssNodes as $inlineCssNode) {

			$oldInlineCss = $inlineCssNode->getAttribute('style');
			$newInlineCss = $this->processCssContent($oldInlineCss, './');

			$inlineCssNode->setAttribute('style', $newInlineCss);

		}

	}

	/**
	 * .
	 *
	 */
	private function processCss() {

		// For XPath 2.0
		// $cssNodes = $this->DOMXpath->query('//link[lower-case(@rel)="stylesheet"]');
		$cssNodes = $this->DOMXpath->query('//link[@rel="stylesheet"] | //link[@rel="Stylesheet"] | //link[@rel="STYLESHEET"] | //link[@rel="StyleSheet"]');
		$headNode = $this->DOMXpath->query('//head')->item(0);

		foreach ($cssNodes as $cssNode) {

			$oldCssHref = $cssNode->getAttribute('href');

			if (empty($oldCssHref) === false && Http::is_relative_url($oldCssHref)) {

				$newCssHref = './' . $this->settings['cssDirectory'] . '/' . $oldCssHref;

				$this->moveRelatedFile($oldCssHref, $this->settings['cssDirectory']);

				$cssNode->setAttribute('href', $newCssHref);

				$headNode->appendChild($cssNode);

			}

		}

		$cssFiles = new FilesystemIterator($this->settings['location'] . '/' . $this->settings['cssDirectory']);

		foreach ($cssFiles as $cssFile) {

			$this->processCssFile($cssFile);

		}

	}

	/**
	 * .
	 *
	 */
	private function processCssFile($cssFile) {

		if (file_exists($cssFile)) {

			$oldCssContent = file_get_contents($cssFile);
			$newCssContent = $this->processCssContent($oldCssContent, '../');

			file_put_contents($cssFile, $newCssContent);

		}

	}

	/**
	 * .
	 *
	 */
	private function processCssContent($cssContent, $pathPrefix) {

		$patterns = array('~<!--~', '~-->~', '~//-->~');
		$replacements = array('', '', '');

		preg_match_all('~@import url\([\'\"]?(.*?)[\'\"]?\)~', $cssContent, $importMatches1);
		preg_match_all('~@import [\'\"](.*?)[\'\"];~', $cssContent, $importMatches2);
		preg_match_all('~(?<!@import )url\([\'\"]?(.*?)[\'\"]?\)~', $cssContent, $resourceMatches);

		if (isset($importMatches1[1])) {

			foreach ($importMatches1[1] as $importUrl) {

				$cssFile = $this->moveRelatedFile($importUrl, $this->settings['cssDirectory']);
				$this->processCssFile($cssFile);

				$patterns[] = '~@import url\([\'\"]?' . $importUrl . '[\'\"]?\)~';
				$replacements[] = '@import url("' . $importUrl . '")';

			}

		}

		if (isset($importMatches2[1])) {

			foreach ($importMatches2[1] as $importUrl) {

				$cssFile = $this->moveRelatedFile($importUrl, $this->settings['cssDirectory']);
				$this->processCssFile($cssFile);

				$patterns[] = '~@import [\'\"]' . $importUrl . '[\'\"]~';
				$replacements[] = '@import url("' . $importUrl . '")';

			}

		}

		if (isset($resourceMatches[1])) {

			foreach ($resourceMatches[1] as $resourceUrl) {

				if (Http::is_encoded_data($resourceUrl)) {

				} elseif (Http::is_relative_url($resourceUrl)) {

					$newResourceUrl = $resourceUrl;

					$filePath = parse_url(urldecode($resourceUrl), PHP_URL_PATH);

					$fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

					if ($fileExtension === 'eot' || $fileExtension === 'otf' || $fileExtension === 'svg'|| $fileExtension === 'ttf' || $fileExtension === 'woff') {

						$subDirectory = $this->settings['fontsDirectory'];

					} elseif ($fileExtension === 'htc' || $fileExtension === 'js' || $fileExtension === 'xml' || $fileExtension === 'php') {

						// TODO: Process files?
						$subDirectory = $this->settings['jsDirectory'];

					} elseif ($fileExtension === '1') {

						// TODO: .eot.1 may be common but its not the only time a .1 could happen.
						$subDirectory = $this->settings['fontsDirectory'];

						$newResourceUrl = pathinfo($filePath, PATHINFO_FILENAME) . '#iefix';


					} else {

						$subDirectory = $this->settings['imgDirectory'];

					}

					$this->moveRelatedFile($resourceUrl, $subDirectory);

					$patterns[] = '~url\([\'\"]?' . $resourceUrl . '[\'\"]?\)~';
					$replacements[] = 'url("' . $pathPrefix . $subDirectory. '/' . $newResourceUrl . '")';


				} else {

					$patterns[] = '~url\([\'\"]?' . $resourceUrl . '[\'\"]?\)~';
					$replacements[] = 'url("' . $resourceUrl . '")';

				}

			}

		}

		$newCssContent = trim(preg_replace($patterns, $replacements, $cssContent));
		$newCssContent = mb_convert_encoding($newCssContent, 'ASCII', mb_detect_encoding($newCssContent));
		$newCssContent = trim($newCssContent, '?');

		return $newCssContent;
	}

	/**
	 * .
	 *
	 */
	private function processJs() {

		$jsNodes = $this->DOMXpath->query('//script[@src]');

		foreach ($jsNodes as $jsNode) {

			$oldJsSrc = $jsNode->getAttribute('src');

			if (empty($oldJsSrc) === false && Http::is_relative_url($oldJsSrc)) {

				$newJsSrc = './' . $this->settings['jsDirectory'] . '/' . $oldJsSrc;

				$this->moveRelatedFile($oldJsSrc, $this->settings['jsDirectory']);

				$jsNode->setAttribute('src', $newJsSrc);

			}

		}

		$jsFiles = new FilesystemIterator($this->settings['location'] . '/' . $this->settings['jsDirectory']);

		foreach ($jsFiles as $jsFile) {

			$this->processJsFile($jsFile);

		}

	}

	/**
	 * .
	 *
	 */
	private function processJsFile($jsFile) {

		if (file_exists($jsFile)) {

			$oldJContent = file_get_contents($jsFile);
			$newJContent = $this->processJsContent($oldJContent, '../');

			file_put_contents($jsFile, $newJContent);

		}

	}

	/**
	 * .
	 *
	 */
	private function processJsContent($jsContent, $pathPrefix) {
		// TODO: Manually download resources linked in the Javascript because wget does not download them.

		$patterns = array('<!--', '-->', '//-->', '//<![CDATA[', '//]]>');
		$replacements = array('', '', '', '', '');

		preg_match_all('~script.*?src\=[\'\"](.*?)[\'\"]~', $jsContent, $jsMatches);
		preg_match_all('~link.*?rel\=[\'\"].*?[\'\"].*?href\=[\'\"](.*?)[\'\"]~', $jsContent, $cssMatches);
		preg_match_all('~img.*?src\=[\'\"](.*?)[\'\"]~', $jsContent, $imgMatches);

		if (isset($jsMatches[1])) {

			foreach ($jsMatches[1] as $oldJsSrc) {

				if (empty($oldJsSrc) === false && Http::is_relative_url($oldJsSrc)) {

					$newJsSrc = $pathPrefix . $this->settings['jsDirectory'] . '/' . basename($oldJsSrc);

					$this->moveRelatedFile(basename($oldJsSrc), $this->settings['jsDirectory']);

					$patterns[] = $oldJsSrc;
					$replacements[] = $newJsSrc;

				}

			}

		}

		if (isset($cssMatches[1])) {

			foreach ($cssMatches[1] as $oldCssHref) {

				if (empty($oldCssHref) === false && Http::is_relative_url($oldCssHref)) {

					$newCssHref = $pathPrefix . $this->settings['cssDirectory'] . '/' . basename($oldCssHref);

					$this->moveRelatedFile(basename($oldCssHref), $this->settings['cssDirectory']);

					$patterns[] = $oldCssHref;
					$replacements[] = $newCssHref;

				}

			}

		}

		if (isset($imgMatches[1])) {

			foreach ($imgMatches[1] as $oldImgSrc) {

				if (empty($oldImgSrc) === false && Http::is_relative_url($oldImgSrc)) {

					$newImgSrc = $pathPrefix . $this->settings['imgDirectory'] . '/' . basename($oldImgSrc);

					$this->moveRelatedFile(basename($oldImgSrc), $this->settings['imgDirectory']);

					$patterns[] = $oldImgSrc;
					$replacements[] = $newImgSrc;

				}

			}
		}

		$newJsContent = trim(str_replace($patterns, $replacements, $jsContent));

		return $newJsContent;

	}

	/**
	 * .
	 *
	 */
	private function save_html() {

		$htmlFilePath = $this->settings['location'] . '/' . $this->settings['htmlFilename'];

		$this->DOMDocument->saveHTMLFile($htmlFilePath);
		$htmlContent = Tidyp::repairFile($htmlFilePath, true);
		$htmlContent = str_replace(array($this->tmpHtmlFilename . '#', $this->tmpHtmlFilename), array('#', $this->url), $htmlContent);

		file_put_contents($htmlFilePath, $htmlContent);

	}

	/**
	 * .
	 *
	 */
	private function finish() {

		if (FileSystem::is_dir_empty($this->settings['location'] . '/' . $this->settings['tmpDirectory']) === false) {

			trigger_error('finish(): ', E_USER_WARNING);

		} else {

			rmdir($this->settings['location'] . '/' . $this->settings['tmpDirectory']);

		}

	}

	/**
	 * .
	 *
	 */
	public function download($url, $user = '', $pass = '') {

		set_time_limit(0);

		$this->url = $url;
		$this->user = $user;
		$this->pass = $pass;

		$this->prepare_directories();
		// TODO: Find a better way to systematically get the name of the primary HTML file downloaded OR get wget to download it as a different name. --output-document is not intended to be used this way and is not supported in conjunction with --page-requisites.
		$this->setTmpHtmlFilename();
		$this->download_files();
		$this->processHtml();
		$this->convertScriptBlocks();
		$this->convertStyleBlocks();
		$this->processComments();
		$this->processImages();
		$this->processFlash();
		$this->processFavicon();
		$this->processInlineCss();
		$this->processCss();
		$this->processJs();
		$this->save_html();
		$this->finish();

	}

}

class Tidyp {

	/**
	 * Properly format an HTML file.
	 *
	 * @param string $file The path to the HTML file.
	 * @param bool $strict Format additional elements.
	 */
	public static function repairFile($file, $strict = false) {

		// TODO: bugging out tags breaks some times
		$originalEmptyTags = array('<span', 'span>', '<i>', '<i ', '/i>');
		$tmpEmptyTags = array('<tidyspan', 'tidyspan>', '<tidyi>', '<tidyi ', '/tidyi>');

		$html = file_get_contents($file);
		$html = str_replace($originalEmptyTags, $tmpEmptyTags, $html);

		$config = array(
			'alt-text' 	          => '',
			'drop-empty-paras'    => false,
			'new-blocklevel-tags' => 'article aside audio details dialog figcaption figure footer header hgroup nav section source summary track video',
			'new-empty-tags'      => 'command embed keygen source track wbr',
			'new-inline-tags'     => 'canvas command data datalist embed keygen mark meter output progress time wbr tidyspan tidyi',
			'output-html'         => true,
			'break-before-br'     => true,
			'indent'              => true,
			'indent-spaces'       => 4,
			'sort-attributes'     => 'alpha',
			'wrap'                => 0,
		);

		$Tidy = new tidy;
		$Tidy->parseString($html, $config);

		$html = $Tidy->value;

		$html = str_replace($tmpEmptyTags, $originalEmptyTags, $html);

		$html = preg_replace('| {4}|', "\t", $html);
		$html = preg_replace('|<li(.*?)>\s*<a|', '<li$1><a', $html);
		$html = preg_replace('|<\/a>\s*<\/li|', '</a></li', $html);
		$html = preg_replace('|\s*<\/script>|', '</script>', $html);

		if ($strict) {

			$html = self::repairOpenNocripts($html);
			$html = self::repairCloseNocripts($html);
			$html = self::repairOpenComments($html);
			$html = self::repairCloseComments($html);
			$html = self::repairOpenScripts($html);
			$html = self::repairCloseScripts($html);

		} else {

			$html = preg_replace('|^(\t*)(.*?)><\!--|m', '$1$2>' ."\n". '$1<!--', $html);
			$html = preg_replace('|^(\t*)<\!--(.*?)<\!\[|ms', '$1<!--$2$1<![', $html);
			$html = preg_replace('|^(\t*)(.*?)><script|m', '$1$2>' ."\n". '$1<script', $html);
			$html = preg_replace('|^(\t*)(.*?)><noscript|m', '$1$2>' ."\n". '$1<noscript', $html);
			$html = preg_replace('|^(\t*)(.*?)><\/noscript|m', '$1$2>' ."\n". '$1</noscript', $html);

		}

		return $html;

	}

	/**
	 * Indents opening script tags properly
	 *
	 * @param string $html The HTML to be tidied.
	 * @return string Returns tidied HTML.
	 */
	public static function repairOpenScripts($html) {

		$count = preg_match_all('|(\t*)(.*?)><script|m', $html, $matches);

		if ($count >= 1) {

			$html = preg_replace('|^(\t*)(.*?)><script|m', '$1$2>' ."\n". '$1<script', $html);

			return self::repairOpenScripts($html);

		} else {

			return $html;

		}

	}

	/**
	 * Indents closing script tags properly
	 *
	 * @param string $html The HTML to be tidied.
	 * @return string Returns tidied HTML.
	 */
	public static function repairCloseScripts($html) {

		return $html;

	}

	/**
	 * Indents opening noscript tags properly
	 *
	 * @param string $html The HTML to be tidied.
	 * @return string Returns tidied HTML.
	 */
	public static function repairOpenNocripts($html) {

		$count = preg_match_all('|(\t*)(.*?)><noscript|m', $html, $matches);

		if ($count >= 1) {

			$html = preg_replace('|^(\t*)(.*?)><noscript|m', '$1$2>' ."\n". '$1<noscript', $html);

			return self::repairOpenNocripts($html);

		} else {

			return $html;

		}

	}

	/**
	 * Indents closing noscript tags properly
	 *
	 * @param string $html The HTML to be tidied.
	 * @return string Returns tidied HTML.
	 */
	public static function repairCloseNocripts($html) {

		$count = preg_match_all('|(\t*)(.*?)><\/noscript|m', $html, $matches);

		if ($count >= 1) {

			$html = preg_replace('|^(\t*)(.*?)><\/noscript|m', '$1$2>' ."\n". '$1</noscript', $html);

			return self::repairCloseNocripts($html);

		} else {

			return $html;

		}

	}

	/**
	 * Indents opening comments properly
	 *
	 * @param string $html The HTML to be tidied.
	 * @return string Returns tidied HTML.
	 */
	public static function repairOpenComments($html) {

		$count = preg_match_all('|(\t*)(.*?)><\!--|m', $html, $matches);

		if ($count >= 1) {

			$html = preg_replace('|^(\t*)(.*?)><\!--|m', '$1$2>' ."\n". '$1<!--', $html);

			return self::repairOpenComments($html);

		} else {

			return $html;

		}

	}

	/**
	 * Indents closing comments properly
	 *
	 * @param string $html The HTML to be tidied.
	 * @return string Returns tidied HTML.
	 */
	public static function repairCloseComments($html) {

		$count = preg_match_all('|(\t*)<\!--(.*?)<\!\[|ms', $html, $matches);

		if ($count >= 1) {

			$html = preg_replace('|^(\t*)<\!--(.*?)<\!\[|ms', '$1<!--$2$1<![', $html);

			return self::repairCloseNocripts($html);

		} else {

			return $html;

		}

	}

}
