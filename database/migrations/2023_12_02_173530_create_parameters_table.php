<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('parent')->nullable();
            $table->string('pointer')->nullable(); // 1.1. / 2.3.
            $table->timestamps();
        });

        $records = json_decode(file_get_contents(database_path('request.json')), true);
        $parent = null;
        foreach ($records as $key => $value) {
            if (substr_count($key, '.') == 1) {
                $parent = null;
            }
            $item = \App\Models\Parameter::create([
                'name'      => $value,
                'parent'    => $parent,
                'pointer'   => $key,
            ]);
            if (substr_count($key, '.') == 1) {
                $parent = $item->id;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameters');
    }
};
