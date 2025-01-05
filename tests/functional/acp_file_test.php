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

use DirectoryIterator;
use phpbb_functional_test_case;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @group functional
 */
class acp_file_test extends phpbb_functional_test_case
{
	private string $path;

	protected static function setup_extensions(): array
	{
		return ['phpbb/pwakit'];
	}

	protected function setUp(): void
	{
		if (getenv('GITHUB_ACTIONS') !== 'true')
		{
			$this->markTestSkipped('This test is skipped on local test servers since they may not always work for uploading.');
		}

		parent::setUp();

		$this->path = __DIR__ . '/../fixtures/';
		$this->add_lang('posting');
		$this->add_lang_ext('phpbb/pwakit', ['acp_pwa', 'info_acp_pwa']);
	}

	protected function tearDown(): void
	{
		$iterator = new DirectoryIterator(__DIR__ . '/../../../../../images/site_icons/');
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

	private function upload_file($filename, $mimetype): Crawler
	{
		// Request ACP index for correct URL
		self::request('GET', 'adm/index.php?sid=' . $this->sid);

		// self::$client->request remembers the adm/ part from the above request (or prior admin_login())
		$url = 'index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid;

		$crawler = self::$client->request('GET', $url);
		$this->assertContainsLang('ACP_PWA_KIT_SETTINGS', $crawler->text());

		$file_form_data = array_merge(['upload' => $this->lang('ACP_PWA_IMG_UPLOAD_BTN')], $this->get_hidden_fields($crawler, $url));

		$file = [
			'tmp_name' => $this->path . $filename,
			'name' => $filename,
			'type' => $mimetype,
			'size' => filesize($this->path . $filename),
			'error' => UPLOAD_ERR_OK,
		];

		return self::$client->request(
			'POST',
			$url,
			$file_form_data,
			['pwa_upload' => $file]
		);
	}

	public function test_upload_empty_file()
	{
		$this->login();
		$this->admin_login();

		$crawler = $this->upload_file('empty.png', 'image/png');

		$this->assertEquals($this->lang('EMPTY_FILEUPLOAD'), $crawler->filter('div.errorbox > p')->text());
	}

	public function test_upload_invalid_extension()
	{
		$this->login();
		$this->admin_login();

		$crawler = $this->upload_file('foo.gif', 'image/gif');

		$this->assertEquals($this->lang('DISALLOWED_EXTENSION', 'gif'), $crawler->filter('div.errorbox > p')->text());
	}

	public function test_upload_valid_file()
	{
		// Check icon does not yet appear in the html tags
		$crawler = self::request('GET', 'index.php');
		$this->assertCount(0, $crawler->filter('link[rel="apple-touch-icon"]'));

		$this->login();
		$this->admin_login();

		$crawler = $this->upload_file('foo.png', 'image/png');

		// Ensure there was no error message rendered
		$this->assertContainsLang('ACP_PWA_IMG_UPLOAD_SUCCESS', $crawler->text());

		// Check icon appears in the ACP as expected
		$crawler = self::request('GET', 'adm/index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid);
		$this->assertStringContainsString('foo.png', $crawler->filter('fieldset')->eq(2)->text());

		// Check icon appears in the html tags as expected
		$crawler = self::request('GET', 'index.php?sid=' . $this->sid);
		$this->assertStringContainsString('foo.png', $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));
	}

	public function test_resync_delete_file()
	{
		// Manually copy image to site icon dir
		copy($this->path . 'bar.png', __DIR__ . '/../../../../../images/site_icons/bar.png');

		// Check icon does not appear in the html tags
		$crawler = self::request('GET', 'index.php');
		$this->assertCount(0, $crawler->filter('link[rel="apple-touch-icon"]'));

		$this->login();
		$this->admin_login();

		// Ensure copied image does not appear
		$crawler = self::request('GET', 'adm/index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid);
		$this->assertContainsLang('ACP_PWA_KIT_NO_ICONS', $crawler->filter('fieldset')->eq(2)->html());

		// Resync image
		$form = $crawler->selectButton('resync')->form();
		$crawler = self::submit($form);
		$this->assertStringContainsString('bar.png', $crawler->filter('fieldset')->eq(2)->text());

		// Check icon appears in the html tags as expected
		$crawler = self::request('GET', 'index.php?sid=' . $this->sid);
		$this->assertStringContainsString('bar.png', $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));

		// Delete image
		$crawler = self::request('GET', 'adm/index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid);
		$form = $crawler->selectButton('delete')->form(['delete' => 'bar.png']);
		$crawler = self::submit($form);
		$form = $crawler->selectButton('confirm')->form(['delete' => 'bar.png']);
		$crawler = self::submit($form);
		$this->assertStringContainsString($this->lang('ACP_PWA_IMG_DELETED', 'bar.png'), $crawler->text());
		$crawler = self::request('GET', 'adm/index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid);
		$this->assertContainsLang('ACP_PWA_KIT_NO_ICONS', $crawler->filter('fieldset')->eq(2)->html());

		// Check icon does not appear in the html tags
		$crawler = self::request('GET', 'index.php');
		$this->assertCount(0, $crawler->filter('link[rel="apple-touch-icon"]'));
	}
}
