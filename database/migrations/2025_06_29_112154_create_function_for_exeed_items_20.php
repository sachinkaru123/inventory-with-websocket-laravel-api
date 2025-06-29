<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       //pgsql db function
       DB::unprepared( <<<SQL
        CREATE OR REPLACE FUNCTION check_items_count()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Check the total number of rows in the items table
                IF (SELECT COUNT(*) FROM items) >= 20 THEN
                    -- Send a notification to the application
                    PERFORM pg_notify('items_count_reached', 'Items count has reached 20');
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            -- Create a trigger that calls the function after an insert operation on the items table
            CREATE TRIGGER items_count_trigger
            AFTER INSERT ON items
            FOR EACH ROW
            EXECUTE FUNCTION check_items_count();
       SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(
            'DROP FUNCTION IF EXISTS check_items_count()'
        );
    }
};
