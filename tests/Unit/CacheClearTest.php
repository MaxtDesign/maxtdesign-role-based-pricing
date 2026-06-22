<?php
/**
 * Covers the request-scoped in-memory storage reset that is STEP 1 (and the
 * "MOST CRITICAL" step) of clear_cache_after_order() — the guard against the
 * rapid-order exploit where Order 2 reused Order 1's cached pricing held in
 * static memory, allowing fraudulent sub-$1 purchases.
 *
 * @package MaxtDesign_RBP
 */

use PHPUnit\Framework\TestCase;

final class CacheClearTest extends TestCase {

    private function set_static($prop, $value) {
        $p = new ReflectionProperty(MaxtDesign_Role_Based_Pricing::class, $prop);
        $p->setAccessible(true);
        $p->setValue(null, $value);
    }

    private function get_static($prop) {
        $p = new ReflectionProperty(MaxtDesign_Role_Based_Pricing::class, $prop);
        $p->setAccessible(true);
        return $p->getValue();
    }

    public function testClearInMemoryStorageResetsPricesAndDiscountFlag() {
        // Simulate Order 1 having populated request-scoped pricing state.
        $this->set_static('original_prices', array(123 => 499.0, 456 => 50.0));
        $this->set_static('user_has_discounts', true);

        MaxtDesign_Role_Based_Pricing::clear_in_memory_storage();

        $this->assertSame(array(), $this->get_static('original_prices'), 'Original prices must be flushed after an order.');
        $this->assertFalse($this->get_static('user_has_discounts'), 'Discount flag must reset after an order.');
    }
}
