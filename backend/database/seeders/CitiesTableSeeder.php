<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cities = collect([
            "الاحساء",
            "الأفلاج",
            "الباحة",
            "الجبيل",
            "الحوطة",
            "الحوية",
            "الخبر",
            "الخرج",
            "الدمام",
            "الدوادمي",
            "الرس",
            "الرياض",
            "الزلفي",
            "الظهران",
            "الفريش",
            "القريات",
            "القطيف",
            "المدينة",
            "أحد رفيدة",
            "بارق",
            "بحره",
            "بريدة",
            "بيشه",
            "تاروت",
            "تبوك",
            "جدة",
            "جيزان",
            "حفر الباطن",
            "خميس مشيط",
            "سكاكا",
            "سيهات",
            "شروره",
            "صبياء",
            "الطائف",
            "عرعر",
            "عسير",
            "عنيزة",
            "مكة",
            "تجران",
            "وادي الدواسر",
            "ينبع البحر",
        ]);

        $cities->each(function($city) {
            City::firstOrCreate(['name_ar' => $city], [
                'name' => $city,
                'name_ar' => $city,
            ]);
        });
    }
}
