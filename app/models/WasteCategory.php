<?php
/**
 * WasteCategory entity - General, Recyclable, Organic and so on.
 *
 * Author : Ong Kar Heng (2408830)
 * Module : Bin & Location Management
 */
class WasteCategory extends Model
{
    protected static string $table      = 'waste_categories';
    protected static string $primaryKey = 'category_id';
    protected static array  $columns    = ['category_name', 'description'];

    public function getCategoryName(): string { return (string) $this->get('category_name'); }
    public function getDescription(): ?string { return $this->get('description'); }
}
