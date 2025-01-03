<?php

/**
 *
 * Progressive Web App Kit. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2024 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\pwakit\controller;

use phpbb\config\config;
use phpbb\exception\runtime_exception;
use phpbb\language\language;
use phpbb\pwakit\helper\helper;
use phpbb\pwakit\helper\upload;
use phpbb\request\request;
use phpbb\template\template;

class admin_controller
{
	/** @var string $id */
	public string $id;

	/** @var string $u_action */
	public string $u_action;

	/** @var config $config */
	protected config $config;

	/** @var helper $helper */
	protected helper $helper;

	/** @var language $language */
	protected language $language;

	/** @var request $request */
	protected request $request;

	/** @var template $template */
	protected template $template;

	/** @var upload */
	protected upload $upload;

	/** @var string $phpbb_root_path */
	protected string $phpbb_root_path;

	/** @var array $errors */
	protected array $errors = [];

	/**
	 * Constructor
	 *
	 * @param config $config
	 * @param language $language
	 * @param request $request
	 * @param template $template
	 * @param helper $helper
	 * @param upload $upload
	 * @param $phpbb_root_path
	 */
	public function __construct(config $config, language $language, request $request, template $template, helper $helper, upload $upload, $phpbb_root_path)
	{
		$this->config = $config;
		$this->helper = $helper;
		$this->upload = $upload;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->phpbb_root_path = $phpbb_root_path;

		$this->language->add_lang('acp/board');
		$this->language->add_lang('acp_pwa', 'phpbb/pwakit');
		$this->language->add_lang('posting'); // Used by upload() file errors
	}

	/**
	 * Set page url
	 *
	 * @param string $u_action	Custom form action
	 * @return void
	 */
	public function set_page_url(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	/**
	 * Main ACP module
	 *
	 * @param string $id
	 * @param string $mode
	 * @return void
	 */
	public function main(string $id, string $mode = ''): void
	{
		$this->id = $id;

		if ($mode !==  'settings')
		{
			return;
		}

		$form_key = 'acp_pwakit';
		add_form_key($form_key);

		$submit = $this->request->is_set_post('submit');
		$upload = $this->request->is_set_post('upload');
		$resync = $this->request->is_set_post('resync');
		$delete = $this->request->is_set_post('delete');

		if ($submit || $upload || $resync)
		{
			if (!check_form_key($form_key))
			{
				$this->error($this->language->lang('FORM_INVALID'));
			}

			if ($upload)
			{
				$this->upload();
			}
			else if ($resync)
			{
				$this->helper->resync_icons();
			}
			else
			{
				$this->save_settings();
			}
		}
		else if ($delete)
		{
			$this->delete();
		}

		$this->display_settings();
	}

	/**
	 * Display settings
	 *
	 * @return void
	 */
	protected function display_settings(): void
	{
		$this->template->assign_vars([
			'SITE_NAME'			=> $this->config->offsetGet('sitename'),
			'SITE_NAME_SHORT'	=> $this->config->offsetGet('sitename_short') ?: $this->trim_name($this->config->offsetGet('sitename'), 0, 12),
			'PWA_BG_COLOR'		=> $this->config->offsetGet('pwa_bg_color'),
			'PWA_THEME_COLOR'	=> $this->config->offsetGet('pwa_theme_color'),
			'PWA_IMAGES_DIR'	=> $this->helper->get_storage_path(),
			'PWA_KIT_ICONS'		=> $this->helper->get_icons($this->phpbb_root_path),
			'U_ACTION'			=> $this->u_action,
		]);

		$this->display_errors();
	}

	/**
	 * Save settings
	 *
	 * @return void
	 */
	protected function save_settings(): void
	{
		$config_array = [
			'pwa_bg_color'		=> $this->request->variable('pwa_bg_color', ''),
			'pwa_theme_color'	=> $this->request->variable('pwa_theme_color', ''),
		];

		foreach ($config_array as $config_value)
		{
			$this->validate_hex_color($config_value);
		}

		if ($this->has_errors())
		{
			return;
		}

		foreach ($config_array as $config_name => $config_value)
		{
			$this->config->set($config_name, $config_value);
		}

		$this->success('CONFIG_UPDATED');
	}

	/**
	 * Are there any errors?
	 *
	 * @return bool
	 */
	protected function has_errors(): bool
	{
		return (bool) count($this->errors);
	}

	/**
	 * Display any errors
	 *
	 * @return void
	 */
	protected function display_errors(): void
	{
		$has_errors = $this->has_errors();

		$this->template->assign_vars([
			'S_ERROR'	=> $has_errors,
			'ERROR_MSG'	=> $has_errors ? implode('<br>', $this->errors) : '',
		]);
	}

	/**
	 * Validate HTML color hex codes
	 *
	 * @param string $code
	 * @return void
	 */
	protected function validate_hex_color(string $code): void
	{
		$code = trim($code);

		if ($code === '')
		{
			return;
		}

		$test = (bool) preg_match('/^#([0-9A-F]{3}){1,2}$/i', $code);

		if ($test === false)
		{
			$this->errors[] = $this->language->lang('ACP_PWA_INVALID_COLOR', $code);
		}
	}

	/**
	 * Upload image
	 *
	 * @return void
	 */
	protected function upload(): void
	{
		try
		{
			$this->upload->upload();
		}
		catch (runtime_exception $e)
		{
			$this->upload->remove();

			$this->errors[] = $this->language->lang($e->getMessage());
		}

		if ($this->has_errors())
		{
			return;
		}

		$this->success('ACP_PWA_IMG_UPLOAD_SUCCESS');
	}

	/**
	 * Delete image
	 *
	 * @return void
	 */
	protected function delete(): void
	{
		$path = $this->request->variable('delete', '');

		if (confirm_box(true))
		{
			try
			{
				$result = $this->helper->delete_icon($path);
				$this->success($this->language->lang('ACP_PWA_IMG_DELETED', $result));
			}
			catch (runtime_exception $e)
			{
				$this->error($this->language->lang('ACP_PWA_IMG_DELETE_ERROR', $this->language->lang($e->getMessage())));
			}
		}
		else
		{
			confirm_box(false, 'ACP_PWA_IMG_DELETE', build_hidden_fields(array(
				'i'			=> $this->id,
				'mode'		=> 'settings',
				'delete'	=> $path,
				'action'	=> $this->u_action,
			)));
		}
	}

	/**
	 * Trim name, accounting for multibyte and emoji chars
	 *
	 * @param string $string
	 * @param int $start
	 * @param int $length
	 * @return string
	 */
	protected function trim_name(string $string, int $start, int $length): string
	{
		// Check if string contains any HTML entities
		if (str_contains($string, '&') && preg_match('/&[#a-zA-Z0-9]+;/', $string))
		{
			$decoded = html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');

			$trimmed = utf8_substr($decoded, $start, $length);

			return htmlspecialchars($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}

		// If no HTML entities, just trim the string directly
		return utf8_substr($string, $start, $length);
	}

	/**
	 * Trigger success message
	 *
	 * @param string $msg Message lang key
	 * @return void
	 */
	protected function success(string $msg): void
	{
		trigger_error($this->language->lang($msg) . adm_back_link($this->u_action));
	}

	/**
	 * Trigger error message
	 *
	 * @param string $msg Message lang key
	 * @return void
	 */
	protected function error(string $msg): void
	{
		trigger_error($this->language->lang($msg) . adm_back_link($this->u_action), E_USER_WARNING);
	}
}
