<?php

namespace Drupal\webform\Tests\Handler;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Tests\WebformTestBase;

/**
 * Tests for advanced email webform handler functionality with HTML and attachments.
 *
 * @group Webform
 */
class WebformHandlerEmailAdvancedTest extends WebformTestBase {

  protected static $modules = ['filter', 'file', 'webform'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_handler_email_advanced'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create users.
    $this->createUsers();
  }

  /**
   * Create webform test users.
   */
  protected function createUsers() {
    // Create filter.
    $this->createFilters();

    $this->normalUser = $this->drupalCreateUser([
      'access user profiles',
      $this->basicHtmlFilter->getPermissionName(),
    ]);
    $this->adminWebformUser = $this->drupalCreateUser([
      'access user profiles',
      'administer webform',
      'administer users',
      $this->basicHtmlFilter->getPermissionName(),
    ]);
    $this->adminSubmissionUser = $this->drupalCreateUser([
      'access user profiles',
      'administer webform submission',
      $this->basicHtmlFilter->getPermissionName(),
    ]);
  }

  /**
   * Test advanced email handler.
   *
   * Note:
   * The TestMailCollector extends PhpMail, therefore the HTML body
   * will still be escaped, which is why we are looking at the params.body.
   *
   * @see \Drupal\Core\Mail\Plugin\Mail\TestMailCollector
   */
  public function testAdvancedEmailHandler() {
    /** @var \Drupal\webform\WebformInterface $webform_email_advanced */
    $webform_email_advanced = Webform::load('test_handler_email_advanced');

    // Generate a test submission with a file upload.
    $this->drupalLogin($this->adminWebformUser);

    // Post a new submission using test webform which will automatically
    // upload file.txt.
    $edit = [
      'first_name' => 'John',
      'last_name' => 'Smith',
      'email' => 'from@example.com',
      'subject' => 'Subject',
      'message[value]' => '<p><em>Please enter a message.</em> Test that double "quotes" are not encoded.</p>',
    ];
    $this->drupalPostForm('webform/' . $webform_email_advanced->id() . '/test', $edit, t('Submit'));
    $sid = $this->getLastSubmissionId($webform_email_advanced);
    $sent_mail = $this->getLastEmail();

    // Check email is HTML.
    $this->assertContains($sent_mail['params']['body'], '<b>First name</b><br/>John<br/><br/>');
    $this->assertContains($sent_mail['params']['body'], '<b>Last name</b><br/>Smith<br/><br/>');
    $this->assertContains($sent_mail['params']['body'], '<b>Email</b><br/><a href="mailto:from@example.com">from@example.com</a><br/><br/>');
    $this->assertContains($sent_mail['params']['body'], '<b>Subject</b><br/>Subject<br/><br/>');
    $this->assertContains($sent_mail['params']['body'], '<b>Message</b><br/><p><em>Please enter a message.</em> Test that double "quotes" are not encoded.</p><br/><br/>');

    // Check email has attachment.
    $this->assertEqual($sent_mail['params']['attachments'][0]['filecontent'], "this is a sample txt file\nit has two lines\n");
    $this->assertEqual($sent_mail['params']['attachments'][0]['filename'], 'file.txt');
    $this->assertEqual($sent_mail['params']['attachments'][0]['filemime'], 'text/plain');

    // Check resend webform includes link to the attachment.
    $this->drupalGet("admin/structure/webform/manage/test_handler_email_advanced/submission/$sid/resend");
    $this->assertRaw('<span class="file file--mime-text-plain file--text">');
    $this->assertRaw('file.txt');

    // Check resend webform with custom message.
    $this->drupalPostForm("admin/structure/webform/manage/test_handler_email_advanced/submission/$sid/resend", ['message[body]' => 'Testing 123...'], t('Resend message'));
    $sent_mail = $this->getLastEmail();
    $this->assertNotContains($sent_mail['params']['body'], '<b>First name</b><br/>John<br/><br/>');
    $this->assertEqual($sent_mail['params']['body'], 'Testing 123...');

    // Check resent email has the same attachment.
    $this->assertEqual($sent_mail['params']['attachments'][0]['filecontent'], "this is a sample txt file\nit has two lines\n");
    $this->assertEqual($sent_mail['params']['attachments'][0]['filename'], 'file.txt');
    $this->assertEqual($sent_mail['params']['attachments'][0]['filemime'], 'text/plain');

    // Check excluding files.
    $handler = $webform_email_advanced->getHandler('email');
    $handler->setConfiguration(['excluded_elements' => ['file' => 'file']]);
    $webform_email_advanced->save();

    $this->drupalPostForm('webform/' . $webform_email_advanced->id() . '/test', [], t('Submit'));
    $sent_mail = $this->getLastEmail();
    $this->assertFalse(isset($sent_mail['params']['attachments'][0]['filecontent']));
  }

}
