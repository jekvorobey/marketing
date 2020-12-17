<?php

use Illuminate\Database\Seeder;
use App\Models\Certificate\Design;

class GiftCardDesignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'Осень' => 'https://dev-front.ibt-mas.greensight.ru/storage/certificate/3h5hJYVKN6sBlVz2vhHpTqrMDPsHuUhPkwe8y84a.jpg',
            'Космос' => 'https://dev_front.ibt-mas.greensight.ru/storage/certificate/3xDgoNJlBJKU34Gs036ETjBRzJ77el4JtnXZE709.jpg',
            'Лесная тропа' => 'https://dev_front.ibt-mas.greensight.ru/storage/certificate/ja8dEIfLlLt1iUbC3TWhIrbH7IjfPHQ0rObRNEr7.jpg',
            'По умолчанию' => 'https://dev_front.ibt-mas.greensight.ru//storage/certificate/qGF8EzK0UPy4id4icuN7jWrDAiINXVkxjuBoH1hl.png',
            'Горы' => 'https://dev_front.ibt-mas.greensight.ru/storage/certificate/7sddtrv52zEtd8i4BV9R8ubLtEJjANiMdefBHJSn.jpg',
        ];

        foreach ($data as $name => $preview)
        {
            if (Design::query()->where('name', $name)->exists())
                continue;

            Design::query()->create([
                'name' => $name,
                'preview' => $preview,
                'status' => true
            ]);
        }
    }
}
