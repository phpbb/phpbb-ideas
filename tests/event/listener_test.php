<?php
/**
 *
 * Ideas extension for the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbb\ideas\event;

class listener_test extends \phpbb_test_case
{
	/** @var \PHPUnit_Framework_MockObject_MockObject|\phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \PHPUnit_Framework_MockObject_MockObject|\phpbb\controller\helper */
	protected $helper;

	/** @var \PHPUnit_Framework_MockObject_MockObject|\phpbb\ideas\factory\ideas */
	protected $ideas;

	/** @var \phpbb\language\language */
	protected $lang;

	/** @var \PHPUnit_Framework_MockObject_MockObject|\phpbb\ideas\factory\linkhelper */
	protected $link_helper;

	/** @var \PHPUnit_Framework_MockObject_MockObject|\phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $php_ext;

	/**
	 * Setup test environment
	 */
	public function setUp()
	{
		parent::setUp();

		global $phpbb_root_path, $phpEx;

		// Load/Mock classes required by the event listener class
		$this->auth = $this->getMockBuilder('\phpbb\auth\auth')
			->disableOriginalConstructor()
			->getMock();
		$this->config = new \phpbb\config\config(array('ideas_forum_id' => 2));
		$this->helper = $this->getMockBuilder('\phpbb\controller\helper')
			->disableOriginalConstructor()
			->getMock();
		$this->ideas = $this->getMockBuilder('\phpbb\ideas\factory\ideas')
			->disableOriginalConstructor()
			->getMock();
		$lang_loader = new \phpbb\language\language_file_loader($phpbb_root_path, $phpEx);
		$this->lang = new \phpbb\language\language($lang_loader);
		$this->link_helper = $this->getMockBuilder('\phpbb\ideas\factory\linkhelper')
			->disableOriginalConstructor()
			->getMock();
		$this->template = $this->getMockBuilder('\phpbb\template\template')
			->getMock();
		$this->user = new \phpbb\user($this->lang, '\phpbb\datetime');
		$this->php_ext = $phpEx;
	}

	/**
	 * Get the event listener
	 *
	 * @return \phpbb\ideas\event\listener
	 */
	protected function get_listener()
	{
		return new \phpbb\ideas\event\listener(
			$this->auth,
			$this->config,
			$this->helper,
			$this->ideas,
			$this->lang,
			$this->link_helper,
			$this->template,
			$this->user,
			$this->php_ext
		);
	}

	/**
	 * Test the event listener is constructed correctly
	 */
	public function test_construct()
	{
		$this->assertInstanceOf('\Symfony\Component\EventDispatcher\EventSubscriberInterface', $this->get_listener());
	}

	/**
	 * Test the event listener is subscribing events
	 */
	public function test_getSubscribedEvents()
	{
		$this->assertEquals(array(
			'core.viewforum_get_topic_data',
			'core.viewtopic_modify_post_row',
			'core.viewtopic_modify_page_title',
			'core.viewtopic_add_quickmod_option_before',
			'core.viewonline_overwrite_location',
			'core.posting_modify_submit_post_after',
		), array_keys(\phpbb\ideas\event\listener::getSubscribedEvents()));
	}

	/**
	 * Data set for show_post_buttons
	 *
	 * @return array Array of test data
	 */
	public function show_post_buttons_data()
	{
		$post_row = array(
			'U_EDIT'   => true,
			'U_DELETE' => true,
			'U_REPORT' => true,
			'U_WARN'   => true,
			'U_INFO'   => true,
			'U_QUOTE'  => true,
		);

		return array(
			array(2, 1, 1, $post_row, false), // Valid
			array(1, 1, 1, $post_row, true), // Invalid forum
			array(2, 1, 2, $post_row, true), // Invalid post
		);
	}

	/**
	 * Test the show_post_buttons event
	 *
	 * @dataProvider show_post_buttons_data
	 */
	public function test_show_post_buttons($forum_id, $post_id, $first_post_id, $post_row, $expected)
	{
		$listener = $this->get_listener();

		$event = new \phpbb\event\data(array(
			'row' 			=> array(
				'forum_id'	=> $forum_id,
				'post_id'	=> $post_id,
			),
			'post_row'		=> $post_row,
			'topic_data'	=> array('topic_first_post_id' => $first_post_id),
		));

		$listener->show_post_buttons($event);

		$this->assertEquals($expected, $event['post_row']['U_DELETE']);
		$this->assertEquals($expected, $event['post_row']['U_WARN']);

		// These should always be true since we're not changing them
		$this->assertTrue($event['post_row']['U_QUOTE']);
		$this->assertTrue($event['post_row']['U_EDIT']);
		$this->assertTrue($event['post_row']['U_REPORT']);
		$this->assertTrue($event['post_row']['U_INFO']);
	}

	/**
	 * Data set for adjust_quickmod_tools
	 *
	 * @return array Array of test data
	 */
	public function adjust_quickmod_tools_data()
	{
		$quickmod_array = [
			'lock'          => [1 => true],
			'unlock'        => [1 => true],
			'delete_topic'  => [1 => true],
			'restore_topic' => [1 => true],
			'move'          => [1 => true],
			'split'         => [1 => true],
			'merge'         => [1 => true],
			'merge_topic'   => [1 => true],
			'fork'          => [1 => true],
			'make_normal'   => [1 => true],
			'make_sticky'   => [1 => true],
			'make_announce' => [1 => true],
			'make_global'   => [1 => true],
		];

		return [
			[2, $quickmod_array, false], // Valid
			[1, $quickmod_array, true], // Invalid forum
		];
	}

	/**
	 * Test the adjust_quickmod_tools event
	 *
	 * @dataProvider adjust_quickmod_tools_data
	 */
	public function test_adjust_quickmod_tools($forum_id, $quickmod_array, $expected)
	{
		$listener = $this->get_listener();

		$event = new \phpbb\event\data([
			'forum_id' 			=> $forum_id,
			'quickmod_array'	=> $quickmod_array,
		]);

		$listener->adjust_quickmod_tools($event);

		$this->assertEquals($expected, $event['quickmod_array']['delete_topic'][1]);
		$this->assertEquals($expected, $event['quickmod_array']['restore_topic'][1]);
		$this->assertEquals($expected, $event['quickmod_array']['make_normal'][1]);
		$this->assertEquals($expected, $event['quickmod_array']['make_sticky'][1]);
		$this->assertEquals($expected, $event['quickmod_array']['make_announce'][1]);
		$this->assertEquals($expected, $event['quickmod_array']['make_global'][1]);

		// These should always be true since we're not changing them
		$this->assertTrue($event['quickmod_array']['lock'][1]);
		$this->assertTrue($event['quickmod_array']['unlock'][1]);
		$this->assertTrue($event['quickmod_array']['move'][1]);
		$this->assertTrue($event['quickmod_array']['split'][1]);
		$this->assertTrue($event['quickmod_array']['merge'][1]);
		$this->assertTrue($event['quickmod_array']['merge_topic'][1]);
		$this->assertTrue($event['quickmod_array']['fork'][1]);
	}

	/**
	 * Data set for test_viewonline
	 *
	 * @return array Array of test data
	 */
	public function viewonline_data()
	{
		global $phpEx;

		return array(
			// test when on_page is index
			array(
				array(
					1 => 'index',
				),
				array(),
				'$location_url',
				'$location',
				'$location_url',
				'$location',
			),
			// test when on_page is app and session_page is NOT for ideas
			array(
				array(
					1 => 'app',
				),
				array(
					'session_page' => 'app.' . $phpEx . '/foobar'
				),
				'$location_url',
				'$location',
				'$location_url',
				'$location',
			),
			// test when on_page is app and session_page is for ideas
			array(
				array(
					1 => 'app',
				),
				array(
					'session_page' => 'app.' . $phpEx . '/ideas'
				),
				'$location_url',
				'$location',
				'phpbb_ideas_index_controller#a:0:{}',
				'VIEWING_IDEAS',
			),
			// test when on_page is app and session_page is for ideas/post
			array(
				array(
					1 => 'app',
				),
				array(
					'session_page' => 'app.' . $phpEx . '/ideas/post'
				),
				'$location_url',
				'$location',
				'phpbb_ideas_index_controller#a:0:{}',
				'POSTING_NEW_IDEA',
			),
			// test when viewing an idea topic (any topic in forum id 2)
			array(
				array(
					1 => 'viewtopic',
				),
				array(
					'session_forum_id' => 2,
				),
				'$location_url',
				'$location',
				'phpbb_ideas_index_controller#a:0:{}',
				'VIEWING_IDEAS',
			),
			// test when viewing a normal topic (not an idea, so not in forum id 2)
			array(
				array(
					1 => 'viewtopic',
				),
				array(
					'session_forum_id' => 3,
				),
				'$location_url',
				'$location',
				'$location_url',
				'$location',
			),
		);
	}

	/**
	 * Test the viewonline event
	 *
	 * @dataProvider viewonline_data
	 */
	public function test_viewonline($on_page, $row, $location_url, $location, $expected_location_url, $expected_location)
	{
		$this->helper->expects($this->atMost(1))
			->method('route')
			->willReturnCallback(function ($route, array $params = array()) {
				return $route . '#' . serialize($params);
			});

		$listener = $this->get_listener();

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$dispatcher->addListener('core.viewonline_overwrite_location', array($listener, 'viewonline_ideas'));

		$event_data = array('on_page', 'row', 'location_url', 'location');
		$event = new \phpbb\event\data(compact($event_data));
		$dispatcher->dispatch('core.viewonline_overwrite_location', $event);

		$event_data_after = $event->get_data_filtered($event_data);
		foreach ($event_data as $expected)
		{
			$this->assertArrayHasKey($expected, $event_data_after);
		}
		extract($event_data_after);

		$this->assertEquals($expected_location_url, $location_url);
		$this->assertEquals($expected_location, $location);
	}

	/**
	 * Data set for edit_idea_title
	 *
	 * @return array Array of test data
	 */
	public function edit_idea_title_data()
	{
		return array(
			array(
				array(
					'topic_id'       => 1,
					'post_id'        => 1,
					'forum_id'       => 2,
					'mode'           => 'edit',
					'update_subject' => true,
					'post_data'      => array(
						'topic_first_post_id' => 1,
						'post_subject'        => 'Foo Bar 1',
					),
				),
				'once',
			),
			array( // invalid posting mode
				array(
					'topic_id'       => 1,
					'post_id'        => 1,
					'forum_id'       => 2,
					'mode'           => 'post',
					'update_subject' => true,
					'post_data'      => array(
						'topic_first_post_id' => 1,
						'post_subject'        => 'Foo Bar 1',
					),
				),
				'never',
			),
			array( // subject not updated
				   array(
					   'topic_id'       => 1,
					   'post_id'        => 1,
					   'forum_id'       => 2,
					   'mode'           => 'edit',
					   'update_subject' => false,
					   'post_data'      => array(
						   'topic_first_post_id' => 1,
						   'post_subject'        => 'Foo Bar 1',
					   ),
				   ),
				   'never',
			),
			array( // wrong forum
				   array(
					   'topic_id'       => 1,
					   'post_id'        => 1,
					   'forum_id'       => 1,
					   'mode'           => 'edit',
					   'update_subject' => true,
					   'post_data'      => array(
						   'topic_first_post_id' => 1,
						   'post_subject'        => 'Foo Bar 1',
					   ),
				   ),
				   'never',
			),
			array( // not first post
				   array(
					   'topic_id'       => 1,
					   'post_id'        => 2,
					   'forum_id'       => 2,
					   'mode'           => 'edit',
					   'update_subject' => true,
					   'post_data'      => array(
						   'topic_first_post_id' => 1,
						   'post_subject'        => 'Foo Bar 1',
					   ),
				   ),
				   'never',
			),
		);
	}

	/**
	 * Test the edit_idea_title event
	 *
	 * @dataProvider edit_idea_title_data
	 */
	public function test_edit_idea_title($data, $expected)
	{
		$listener = $this->get_listener();

		$event = new \phpbb\event\data($data);

		$this->ideas->expects($this->$expected())
			->method('get_idea_by_topic_id')
			->with($event['topic_id'])
			->willReturn(array('idea_id' => $event['topic_id']));

		$this->ideas->expects($this->$expected())
			->method('set_title')
			->with($event['topic_id'], $event['post_data']['post_subject']);

		$listener->edit_idea_title($event);
	}

	/**
	 * Test data for test_ideas_forum_redirect
	 */
	public function ideas_forum_redirect_data()
	{
		return [
			[2, '$url', true],
			[4, '$url', false],
		];
	}

	/**
	 * Test the ideas_forum_redirect() method
	 *
	 * @dataProvider ideas_forum_redirect_data
	 */
	public function test_ideas_forum_redirect($forum_id, $url, $expected)
	{
		if ($expected)
		{
			$this->setExpectedTriggerError(E_USER_NOTICE, "Redirected to $url");
		}
		$this->helper->expects($expected ? $this->once() : $this->never())
			->method('route')
			->willReturn($url);

		$listener = $this->get_listener();

		$event = new \phpbb\event\data([
			'forum_id' => $forum_id,
		]);

		$listener->ideas_forum_redirect($event);
	}
}

/**
 * Mock redirect()
 * Note: use the same namespace as the ideas
 *
 * @return void
 */
function redirect($url)
{
	trigger_error("Redirected to $url", E_USER_NOTICE);
}
