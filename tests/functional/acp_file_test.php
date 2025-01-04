<?php
/**
 *
 * Progressive Web App Kit. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2024 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\pwakit\tests\functional;

/**
 * @group functional
 */
class acp_file_test extends \phpbb_functional_test_case
{
	private $path;

	protected static function setup_extensions(): array
	{
		return ['phpbb/pwakit'];
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->path = __DIR__ . '/../fixtures/';
		$this->add_lang('posting');
		$this->add_lang_ext('phpbb/pwakit', 'acp_pwa');
		$this->add_lang_ext('phpbb/pwakit', 'info_acp_pwa');
	}

	protected function tearDown(): void
	{
		$iterator = new \DirectoryIterator(__DIR__ . '/../../../../../images/site_icons/');
		foreach ($iterator as $fileinfo)
		{
			if (
				$fileinfo->isDot()
				|| $fileinfo->isDir()
				|| $fileinfo->getFilename() === 'index.htm'
				|| $fileinfo->getFilename() === '.htaccess'
			)
			{
				continue;
			}

			unlink($fileinfo->getPathname());
		}
	}

	private function upload_file($filename, $mimetype)
	{
		$url = 'adm/index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid;

		$crawler = self::$client->request('GET', $url);

		$file_form_data = array_merge(['upload' => $this->lang('ACP_PWA_IMG_UPLOAD_BTN')], $this->get_hidden_fields($crawler, $url));

		$file = [
			'tmp_name' => $this->path . $filename,
			'name' => $filename,
			'type' => $mimetype,
			'size' => filesize($this->path . $filename),
			'error' => UPLOAD_ERR_OK,
		];

		$crawler = self::$client->request(
			'POST',
			$url,
			$file_form_data,
			['pwa_upload' => $file]
		);

		return $crawler;
	}

//	public function test_empty_file()
//	{
//		$this->login();
//
//		$crawler = $this->upload_file('empty.png', 'image/png');
//		$this->assertEquals($this->lang('EMPTY_FILEUPLOAD'), $crawler->filter('p.error')->text());
//	}
//
//	public function test_invalid_extension()
//	{
//		$this->login();
//
//		$crawler = $this->upload_file('illegal-extension.bif', 'application/octet-stream');
//		$this->assertEquals($this->lang('DISALLOWED_EXTENSION', 'bif'), $crawler->filter('p.error')->text());
//	}
//
//	public function test_disallowed_content()
//	{
//		$this->login();
//
//		$crawler = $this->upload_file('disallowed.jpg', 'image/jpeg');
//		$this->assertEquals($this->lang('DISALLOWED_CONTENT'), $crawler->filter('p.error')->text());
//	}
//
//	public function test_disallowed_content_no_check()
//	{
//		$this->login();
//		$this->admin_login();
//		$this->add_lang('ucp');
//
//		// Make sure check_attachment_content is set to false
//		$crawler = self::request('GET', 'adm/index.php?sid=' . $this->sid . '&i=acp_attachments&mode=attach');
//
//		$form = $crawler->selectButton('Submit')->form(array(
//			'config[check_attachment_content]'	=> 0,
//		));
//		self::submit($form);
//
//		// Request index for correct URL
//		self::request('GET', 'index.php?sid=' . $this->sid);
//
//		$crawler = $this->upload_file('disallowed.jpg', 'image/jpeg');
//
//		// Hitting the UNABLE_GET_IMAGE_SIZE error means we passed the
//		// DISALLOWED_CONTENT check
//		$this->assertContainsLang('UNABLE_GET_IMAGE_SIZE', $crawler->text());
//
//		// Reset check_attachment_content to default (enabled)
//		$crawler = self::request('GET', 'adm/index.php?sid=' . $this->sid . '&i=acp_attachments&mode=attach');
//
//		$form = $crawler->selectButton('Submit')->form(array(
//			'config[check_attachment_content]'	=> 1,
//		));
//		self::submit($form);
//	}
//
//	public function test_too_large()
//	{
//		$this->create_user('fileupload');
//		$this->login('fileupload');
//
//		$crawler = $this->upload_file('too-large.png', 'image/png');
//		$this->assertEquals($this->lang('WRONG_FILESIZE', '256', 'KiB'), $crawler->filter('p.error')->text());
//	}

	public function test_valid_file()
	{
		$this->login();
		$this->admin_login();

		$crawler = $this->upload_file('foo.png', 'image/png');

		// Ensure there was no error message rendered
		$this->assertStringNotContainsString('<h2>' . $this->lang('INFORMATION') . '</h2>', self::get_content());

		// Also the file name should be in the first row of the files table
		$this->assertEquals('foo.png', $crawler->filter('fieldset')->eq(3)->text());
	}
}
