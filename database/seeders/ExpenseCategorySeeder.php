<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ExpenseCategory::TYPE_COGS => [
                'Fresh Red Chili',
                'Sun Flower Oil',
                'Onion',
                'Labels',
                'Gloves',
                'Inkwi',
                'Garlic',
                'Ginger',
                'Amabido',
                'Pavro',
                'Green Pepper',
                'Preservatives',
                'Packaging Materials',
            ],
            ExpenseCategory::TYPE_OPERATING => [
                'Employees salaries',
                "Entrepreneur's salary",
                'Rent',
                'Maintenance & repair',
                'Energy',
                'Transportation',
                'Advertisement',
                'Depreciation',
                'Telephone',
            ],
        ];

        foreach ($categories as $type => $names) {
            foreach ($names as $name) {
                ExpenseCategory::query()->firstOrCreate(
                    ['name' => $name, 'type' => $type],
                    ['name' => $name, 'type' => $type]
                );
            }
        }
    }
}
