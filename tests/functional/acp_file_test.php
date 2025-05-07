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
	private string $fixtures;
	private string $icons;

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

		$this->fixtures = __DIR__ . '/../fixtures/';
		$this->icons = __DIR__ . '/../../../../../images/site_icons/';

		$this->add_lang('posting');
		$this->add_lang_ext('phpbb/pwakit', ['acp_pwa', 'info_acp_pwa']);
	}

	protected function tearDown(): void
	{
		$iterator = new DirectoryIterator($this->icons);
		foreach ($iterator as $fileInfo)
		{
			if (
				$fileInfo->isDot()
				|| $fileInfo->isDir()
				|| $fileInfo->getFilename() === 'index.htm'
				|| $fileInfo->getFilename() === '.htaccess'
			)
			{
				continue;
			}

			unlink($fileInfo->getPathname());
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
			'tmp_name' => $this->fixtures . $filename,
			'name' => $filename,
			'type' => $mimetype,
			'size' => filesize($this->fixtures . $filename),
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
		$test_image = 'foo.png';

		// Check icon does not yet appear in the html tags
		$this->assertAppleTouchIconNotPresent();

		$this->login();
		$this->admin_login();

		$crawler = $this->upload_file($test_image, 'image/png');

		// Ensure there was no error message rendered
		$this->assertContainsLang('ACP_PWA_IMG_UPLOAD_SUCCESS', $crawler->text());

		// Check icon appears in the ACP as expected
		$this->assertIconInACP($test_image);

		// Check icon appears in the html tags as expected
		$this->assertAppleTouchIconPresent($test_image);
	}

	public function test_resync_delete_file()
	{
		$test_image = 'bar.png';

		// Manually copy image to site icon dir
		@copy($this->fixtures . $test_image, $this->icons . $test_image);

		// Check icon does not appear in the html tags
		$this->assertAppleTouchIconNotPresent();

		$this->login();
		$this->admin_login();

		// Ensure copied image does not appear in ACP
		$crawler = $this->assertIconsNotInACP();

		// Resync image and then verify icon appears in the html tags as expected
		$this->performResync($crawler, $test_image);
		$this->assertAppleTouchIconPresent($test_image);

		// Delete image
		$this->performDelete($test_image);
		$this->assertIconsNotInACP();

		// Check icon does not appear in the html tags
		$this->assertAppleTouchIconNotPresent();
	}

	/**
	 * Perform the resync action
	 *
	 * @param Crawler $crawler
	 * @param string $expected The name of an icon/image expected to see after resync
	 * @return void
	 */
	private function performResync(Crawler $crawler, string $expected): void
	{
		$form = $crawler->selectButton('resync')->form();
		$crawler = self::submit($form);
		$this->assertStringContainsString($expected, $crawler->filter('fieldset')->eq(3)->text());
	}

	/**
	 * Perform the delete action for an icon
	 *
	 * @param string $icon
	 * @return void
	 */
	private function performDelete(string $icon): void
	{
		$crawler = self::request('GET', 'adm/index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid);
		$form = $crawler->selectButton('delete')->form(['delete' => $icon]);
		$crawler = self::submit($form);
		$form = $crawler->selectButton('confirm')->form(['delete' => $icon]);
		$crawler = self::submit($form);
		$this->assertStringContainsString($this->lang('ACP_PWA_IMG_DELETED', $icon), $crawler->text());
	}

	/**
	 * Assert icon's meta tags do not appear in HTML
	 *
	 * @return void
	 */
	private function assertAppleTouchIconNotPresent(): void
	{
		$crawler = self::request('GET', 'index.php');
		$this->assertCount(0, $crawler->filter('link[rel="apple-touch-icon"]'));
	}

	/**
	 * Assert icon's meta tags appear in HTML
	 *
	 * @param string $icon
	 * @return void
	 */
	private function assertAppleTouchIconPresent(string $icon): void
	{
		$crawler = self::request('GET', 'index.php?sid=' . $this->sid);
		$this->assertStringContainsString($icon, $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));
	}

	/**
	 * Assert icon does not appear in ACP
	 *
	 * @return Crawler
	 */
	private function assertIconsNotInACP(): Crawler
	{
		$crawler = self::request('GET', 'adm/index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid);
		$this->assertContainsLang('ACP_PWA_KIT_NO_ICONS', $crawler->filter('fieldset')->eq(3)->html());
		return $crawler;
	}

	/**
	 * Assert icon appears in ACP
	 *
	 * @param string $icon
	 * @return void
	 */
	private function assertIconInACP(string $icon): void
	{
		$crawler = self::request('GET', 'adm/index.php?i=-phpbb-pwakit-acp-pwa_acp_module&mode=settings&sid=' . $this->sid);
		$this->assertStringContainsString($icon, $crawler->filter('fieldset')->eq(3)->text());
	}
}
