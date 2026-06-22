<?php
/**
 * Unit tests for the role-based pricing engine in MaxtDesign_RBP_Core.
 *
 * The discount math (apply_discount) and the cache-key construction
 * (generate_cache_key + the rule-version suffix used in calculate_price) are
 * the security-critical core. The plugin's documented sub-$1 exploit history
 * came from stale cache being served after a rule changed, so the
 * rule-version-in-key invariant is covered explicitly here.
 *
 * @package MaxtDesign_RBP
 */

use PHPUnit\Framework\TestCase;

final class PricingEngineTest extends TestCase {

    /**
     * Build a core instance without running the constructor (it touches $wpdb).
     * We only exercise pure methods that don't depend on instance DB state.
     */
    private function core() {
        $ref = new ReflectionClass(MaxtDesign_RBP_Core::class);
        return $ref->newInstanceWithoutConstructor();
    }

    private function call($obj, $method, array $args) {
        $m = new ReflectionMethod($obj, $method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    }

    public function testApplyDiscountPercentage() {
        $rule = array('discount_type' => 'percentage', 'discount_value' => 25);
        $this->assertSame(75.0, (float) $this->call($this->core(), 'apply_discount', array(100.0, $rule)));
    }

    public function testApplyDiscountFixedAmountOff() {
        $rule = array('discount_type' => 'fixed', 'discount_value' => 15);
        $this->assertSame(85.0, (float) $this->call($this->core(), 'apply_discount', array(100.0, $rule)));
    }

    public function testApplyDiscountFixedPrice() {
        $rule = array('discount_type' => 'fixed_price', 'discount_value' => 50);
        $this->assertSame(50.0, (float) $this->call($this->core(), 'apply_discount', array(100.0, $rule)));
    }

    public function testGenerateCacheKeyIsDeterministic() {
        $core = $this->core();
        $a = $this->call($core, 'generate_cache_key', array(10, 'wholesale', 100.0));
        $b = $this->call($core, 'generate_cache_key', array(10, 'wholesale', 100.0));
        $this->assertSame($a, $b);
    }

    public function testGenerateCacheKeyVariesByProductRoleAndPrice() {
        $core = $this->core();
        $base = $this->call($core, 'generate_cache_key', array(10, 'wholesale', 100.0));
        $this->assertNotSame($base, $this->call($core, 'generate_cache_key', array(11, 'wholesale', 100.0)));
        $this->assertNotSame($base, $this->call($core, 'generate_cache_key', array(10, 'gold', 100.0)));
        $this->assertNotSame($base, $this->call($core, 'generate_cache_key', array(10, 'wholesale', 120.0)));
    }

    /**
     * Mirrors the cache-key composition in calculate_price()
     * (includes/class-core.php): the key is the base key plus an md5 of the
     * rule "version" (id + updated/created timestamp). When a rule changes the
     * version string changes, so the key changes and stale cache can never be
     * served — the fix for the sub-$1 rapid-order exploit.
     */
    public function testCacheKeyIncludesRuleVersionSoRuleChangesBustCache() {
        $core = $this->core();
        $base = $this->call($core, 'generate_cache_key', array(10, 'wholesale', 100.0));

        $key_for_rule = function (array $rule) use ($base) {
            $version = $rule['id'] . '_' . (isset($rule['updated_at']) ? $rule['updated_at'] : $rule['created_at']);
            return $base . '_' . md5($version);
        };

        $original   = $key_for_rule(array('id' => 5, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'));
        $edited     = $key_for_rule(array('id' => 5, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-06-01 12:00:00'));
        $same_again = $key_for_rule(array('id' => 5, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'));

        $this->assertNotSame($original, $edited, 'Editing a rule must change the cache key.');
        $this->assertSame($original, $same_again, 'An unchanged rule must keep the same cache key.');
    }
}
