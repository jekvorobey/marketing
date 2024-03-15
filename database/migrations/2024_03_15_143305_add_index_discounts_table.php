<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('discount_conditions', static function (Blueprint $table) {
            $table->index(['discount_id', 'discount_condition_group_id'], 'discount_conditions_group_id_discount_id_index');
            $table->index(['discount_id', 'discount_condition_group_id', 'type'], 'discount_conditions_group_id_discount_id_type_index');
        });

        Schema::table('discount_condition_groups', static function (Blueprint $table) {
            $table->index(['discount_id'], 'discount_condition_groups_discount_id_index');
            $table->index(['discount_id', 'logical_operator'], 'discount_condition_groups_discount_id_logical_index');
        });

        Schema::table('discount_user_roles', static function (Blueprint $table) {
            $table->index(['discount_id', 'role_id'], 'discount_user_roles_discount_id_role_id_index');
        });

        Schema::table('discount_segments', static function (Blueprint $table) {
            $table->index(['discount_id', 'segment_id'], 'discount_segments_discount_id_segment_id_index');
        });

        Schema::table('discount_public_events', static function (Blueprint $table) {
            $table->index(['discount_id', 'ticket_type_id'], 'discount_public_events_discount_ticket_type_index');
        });

        Schema::table('discount_promo_code', static function (Blueprint $table) {
            $table->index(['discount_id'], 'discount_promo_code_discount_id_index');
            $table->index(['promo_code_id'], 'discount_promo_code_promo_code_id_promo_id_index');
            $table->index(['discount_id', 'promo_code_id'], 'discount_promo_code_discount_id_promo_id_index');
        });

        Schema::table('discount_product_properties', static function (Blueprint $table) {
            $table->index(['discount_id'], 'discount_product_properties_discount_id_index');
            $table->index(['property_id'], 'discount_product_properties_property_id_index');
        });

        Schema::table('discount_offers', static function (Blueprint $table) {
            $table->index(['offer_id'], 'discount_offers_offer_id_index');
            $table->index(['discount_id', 'offer_id'], 'discount_offers_discount_id_offer_id_index');
        });

        Schema::table('discount_merchants', static function (Blueprint $table) {
            $table->index(['discount_id'], 'discount_merchants_discount_id_index');
            $table->index(['discount_id', 'merchant_id'], 'discount_merchants_discount_id_merchant_id_index');
        });

        Schema::table('discount_categories', static function (Blueprint $table) {
            $table->index(['discount_id', 'category_id'], 'discount_categories_discount_id_category_id_index');
        });

        Schema::table('discount_bundles', static function (Blueprint $table) {
            $table->index(['discount_id', 'bundle_id'], 'discount_bundles_discount_id_bundle_id_index');
        });

        Schema::table('discount_brands', static function (Blueprint $table) {
            $table->index(['discount_id', 'brand_id'], 'discount_brands_discount_id_brand_id_index');
        });

        Schema::table('discount_additional_categories', static function (Blueprint $table) {
            $table->index(['category_id'], 'discount_add_cat_category_id_index');
            $table->index(['category_id', 'discount_category_id'], 'discount_add_cat_category_id_discount_cat_id_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('discount_conditions', static function (Blueprint $table) {
            $table->dropIndex('discount_conditions_group_id_discount_id_index');
            $table->dropIndex('discount_conditions_group_id_discount_id_type_index');
        });
        Schema::table('discount_condition_groups', static function (Blueprint $table) {
            $table->dropIndex('discount_condition_groups_discount_id_index');
            $table->dropIndex('discount_condition_groups_discount_id_logical_index');
        });
        Schema::table('discount_user_roles', static function (Blueprint $table) {
            $table->dropIndex('discount_user_roles_discount_id_role_id_index');
        });
        Schema::table('discount_segments', static function (Blueprint $table) {
            $table->dropIndex('discount_segments_discount_id_segment_id_index');
        });
        Schema::table('discount_public_events', static function (Blueprint $table) {
            $table->dropIndex('discount_public_events_discount_ticket_type_index');
        });
        Schema::table('discount_promo_code', static function (Blueprint $table) {
            $table->dropIndex('discount_promo_code_discount_id_index');
            $table->dropIndex('discount_promo_code_promo_code_id_promo_id_index');
            $table->dropIndex('discount_promo_code_discount_id_promo_id_index');
        });
        Schema::table('discount_product_properties', static function (Blueprint $table) {
            $table->dropIndex('discount_product_properties_discount_id_index');
            $table->dropIndex('discount_product_properties_property_id_index');
        });
        Schema::table('discount_offers', static function (Blueprint $table) {
            $table->dropIndex('discount_offers_offer_id_index');
            $table->dropIndex('discount_offers_discount_id_offer_id_index');
        });
        Schema::table('discount_merchants', static function (Blueprint $table) {
            $table->dropIndex('discount_merchants_discount_id_index');
            $table->dropIndex('discount_merchants_discount_id_merchant_id_index');
        });
        Schema::table('discount_categories', static function (Blueprint $table) {
            $table->dropIndex('discount_categories_discount_id_category_id_index');
        });
        Schema::table('discount_bundles', static function (Blueprint $table) {
            $table->dropIndex('discount_bundles_discount_id_bundle_id_index');
        });
        Schema::table('discount_brands', static function (Blueprint $table) {
            $table->dropIndex('discount_brands_discount_id_brand_id_index');
        });
        Schema::table('discount_additional_categories', static function (Blueprint $table) {
            $table->dropIndex('discount_add_cat_category_id_index');
            $table->dropIndex('discount_add_cat_category_id_discount_cat_id_index');
        });
    }
};
