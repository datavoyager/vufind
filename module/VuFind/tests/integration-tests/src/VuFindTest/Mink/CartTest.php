<?php
/**
 * Mink cart test class.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\Mink;
use Behat\Mink\Element\Element;
use Behat\Mink\Session;

/**
 * Mink cart test class.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class CartTest extends \VuFindTest\Unit\MinkTestCase
{
    use \VuFindTest\Unit\UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return mixed
     */
    public static function setUpBeforeClass()
    {
        return static::failIfUsersExist();
    }

    /**
     * Get a reference to a standard search results page.
     *
     * @param Session $session Mink session
     *
     * @return Element
     */
    protected function getSearchResultsPage(Session $session)
    {
        $path = '/Search/Results?lookfor=id%3A(testsample1+OR+testsample2)';
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Click the "add to cart" button with nothing selected; fail if this does
     * not display an appropriate message.
     *
     * @param Session $session    Mink session
     * @param Element $page       Page element
     * @param Element $updateCart Add to cart button
     *
     * @return void
     */
    protected function tryAddingNothingToCart(Session $session, Element $page,
        Element $updateCart
    ) {
        // This test is a bit timing-sensitive, so introduce a retry loop before
        // completely failing.
        for ($clickRetry = 0; $clickRetry <= 4; $clickRetry++) {
            $updateCart->click();
            $content = $this->findWithWait($session, $page, '.popover-content');
            if (is_object($content)) {
                $this->assertEquals(
                    'No items were selected. '
                    . 'Please click on a checkbox next to an item and try again.',
                    $content->getText()
                );
                return;
            }
        }
        $this->fail('Too many retries on check for error message.');
    }

    /**
     * Add the current page of results to the cart.
     *
     * @param Element $page       Page element
     * @param Element $updateCart Add to cart button
     *
     * @return void
     */
    protected function addCurrentPageToCart(Element $page, Element $updateCart)
    {
        $selectAll = $page->find('css', '#addFormCheckboxSelectAll');
        $selectAll->check();
        $updateCart->click();
    }

    /**
     * Open the cart lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function openCartLightbox(Element $page)
    {
        $viewCart = $page->find('css', '#cartItems');
        $this->assertTrue(is_object($viewCart));
        $viewCart->click();
    }

    /**
     * Set up a generic cart test by running a search and putting its results
     * into the cart, then opening the lightbox so that additional actions may
     * be attempted.
     *
     * @param Session $session Mink session
     *
     * @return Element
     */
    protected function setUpGenericCartTest(Session $session)
    {
        // Activate the cart:
        $this->changeConfigs(
            ['config' =>
                ['Site' => ['showBookBag' => true, 'theme' => 'bootprint3']]
            ]
        );

        $page = $this->getSearchResultsPage($session);

        // Click "add" without selecting anything.
        $updateCart = $this->findWithWait($session, $page, '#updateCart');
        $this->tryAddingNothingToCart($session, $page, $updateCart);

        // Now actually select something:
        $this->addCurrentPageToCart($page, $updateCart);
        $this->assertEquals('2', $this->findWithWait($session, $page, '#cartItems strong')->getText());

        // Open the cart and empty it:
        $this->openCartLightbox($page);

        return $page;
    }

    /**
     * Assert that the open cart lightbox is empty.
     *
     * @param Session $session Mink session
     * @param Element $page    Page element
     *
     * @return void
     */
    protected function checkEmptyCart(Session $session, Element $page)
    {
        $info = $this->findWithWait($session, $page, '.modal-body .form-inline .alert-info');
        $this->assertEquals('Your Book Bag is empty.', $info->getText());
    }

    /**
     * Assert that the "no items were selected" message is visible in the cart
     * lightbox.
     *
     * @param Session $session Mink session
     * @param Element $page    Page element
     *
     * @return void
     */
    protected function checkForNonSelectedMessage(Session $session, Element $page)
    {
        $warning = $this->findWithWait($session, $page, '.modal-body .alert .message');
        $this->assertEquals(
            'No items were selected. '
            . 'Please click on a checkbox next to an item and try again.',
            $warning->getText()
        );
    }

    /**
     * Assert that the "login required" message is visible in the cart lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function checkForLoginMessage(Element $page)
    {
        $warning = $page->find('css', '.modal-body .alert-danger');
        $this->assertTrue(is_object($warning));
        $this->assertEquals(
            'You must be logged in first',
            $warning->getText()
        );
    }

    /**
     * Select all of the items currently in the cart lightbox.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function selectAllItemsInCart(Element $page)
    {
        $cartSelectAll = $page->find('css', '.modal-dialog .checkbox-select-all');
        $cartSelectAll->check();
    }

    /**
     * Test that we can put items in the cart and then remove them with the
     * delete control.
     *
     * @return void
     */
    public function testFillAndDeleteFromCart()
    {
        $session = $this->getMinkSession();
        $session->start();
        $page = $this->setUpGenericCartTest($session);
        $delete = $this->findWithWait($session, $page, '#cart-delete-label');

        // First try deleting without selecting anything:
        $delete->click();
        $this->checkForNonSelectedMessage($session, $page);

        // Now actually select the records to delete:
        $this->selectAllItemsInCart($page);
        $delete->click();
        $deleteConfirm = $this->findWithWait($session, $page, '#cart-confirm-delete');
        $deleteConfirm->click();
        $this->checkEmptyCart($session, $page);

        // Close the lightbox:
        $close = $this->findWithWait($session, $page, 'button.close');
        $close->click();

        // Confirm that the cart has truly been emptied:
        $this->assertEquals('0', $this->findWithWait($session, $page, '#cartItems strong')->getText());

        $session->stop();
    }

    /**
     * Test that we can put items in the cart and then remove them with the
     * empty button.
     *
     * @return void
     */
    public function testFillAndEmptyCart()
    {
        $session = $this->getMinkSession();
        $session->start();
        $page = $this->setUpGenericCartTest($session);

        // Activate the "empty" control:
        $empty = $this->findWithWait($session, $page, '#cart-empty-label');
        $empty->click();
        $emptyConfirm = $this->findWithWait($session, $page, '#cart-confirm-empty');
        $emptyConfirm->click();
        $this->checkEmptyCart($session, $page);

        // Close the lightbox:
        $close = $this->findWithWait($session, $page, 'button.close');
        $close->click();

        // Confirm that the cart has truly been emptied:
        $this->assertEquals('0', $this->findWithWait($session, $page, '#cartItems strong')->getText());

        $session->stop();
    }

    /**
     * Test that the email control works.
     *
     * @return void
     */
    public function testCartEmail()
    {
        $session = $this->getMinkSession();
        $session->start();
        $page = $this->setUpGenericCartTest($session);
        $button = $this->findWithWait($session, $page, '.cart-controls button[name=email]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($session, $page);

        // Now do it for real -- we should get a login prompt.
        $this->selectAllItemsInCart($page);
        $button->click();
        $title = $this->findWithWait($session, $page, '#modalTitle');
        $this->assertEquals('Email Selected Book Bag Items', $title->getText());
        $this->checkForLoginMessage($page);

        // Create an account.
        $this->findWithWait($session, $page, '.modal-body .createAccountLink')->click();
        $this->fillInAccountForm($session, $page);
        $this->findWithWait($session, $page, '.modal-body .btn.btn-primary')->click();

        // Test that we now have an email form.
        $toField = $this->findWithWait($session, $page, '#email_to');
        $this->assertNotNull($toField);

        $session->stop();
    }

    /**
     * Test that the save control works.
     *
     * @return void
     */
    public function testCartSave()
    {
        $session = $this->getMinkSession();
        $session->start();
        $page = $this->setUpGenericCartTest($session);
        $button = $this->findWithWait($session, $page, '.cart-controls button[name=saveCart]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($session, $page);

        // Now do it for real -- we should get a login prompt.
        $this->selectAllItemsInCart($page);
        $button->click();
        $title = $this->findWithWait($session, $page, '#modalTitle');
        $this->assertEquals('Save Selected Book Bag Items', $title->getText());
        $this->checkForLoginMessage($page);

        // Log in to account created in previous test.
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);

        // Save the favorites.
        $submit = $this->findWithWait($session, $page, '.modal-body input[name=submit]');
        $submit->click();
        $result = $this->findWithWait($session, $page, '.modal-body .alert-info');
        $this->assertEquals(
            'Your item(s) were saved successfully', $result->getText()
        );

        // Click the close button.
        $submit = $this->findWithWait($session, $page, '.modal-body .btn');
        $this->assertEquals('close', $submit->getText());
        $submit->click();

        $session->stop();
    }

    /**
     * Test that the export control works.
     *
     * @return void
     */
    public function testCartExport()
    {
        $session = $this->getMinkSession();
        $session->start();
        $page = $this->setUpGenericCartTest($session);
        $button = $this->findWithWait($session, $page, '.cart-controls button[name=export]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($session, $page);

        // Now do it for real -- we should get an export option list:
        $this->selectAllItemsInCart($page);
        $button->click();
        $title = $this->findWithWait($session, $page, '#modalTitle');
        $this->assertEquals('Export Selected Book Bag Items', $title->getText());

        // Select EndNote option
        $select = $this->findWithWait($session, $page, '#format');
        $select->selectOption('EndNote');

        // Do the export:
        $submit = $this->findWithWait($session, $page, '.modal-body input[name=submit]');
        $submit->click();
        $result = $this->findWithWait($session, $page, '.modal-body .alert .text-center .btn');
        $this->assertEquals('Download File', $result->getText());

        $session->stop();
    }

    /**
     * Test that the print control works.
     *
     * @return void
     */
    public function testCartPrint()
    {
        $session = $this->getMinkSession();
        $session->start();
        $page = $this->setUpGenericCartTest($session);
        $button = $this->findWithWait($session, $page, '.cart-controls button[name=print]');

        // First try clicking without selecting anything:
        $button->click();
        $this->checkForNonSelectedMessage($session, $page);

        // Now do it for real -- we should get redirected.
        $this->selectAllItemsInCart($page);
        $button->click();
        list(, $params) = explode('?', $session->getCurrentUrl());
        $this->assertEquals(
            'print=true&id[]=VuFind|testsample1&id[]=VuFind|testsample2', $params
        );

        $session->stop();
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        static::removeUsers('username1');
    }
}
