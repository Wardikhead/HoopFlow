<?php

/** Database migration 2026 11 06 000020 create mv standings. */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Up.
     *
     * @return void Nothing.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement(<<<'SQL'
CREATE MATERIALIZED VIEW mv_standings AS
SELECT
    d.id AS division_id,
    t.id AS team_id,
    COUNT(m.id)::int AS games_played,
    COUNT(*) FILTER (WHERE m.winner_team_id = t.id)::int AS wins,
    COUNT(*) FILTER (WHERE m.winner_team_id IS NOT NULL AND m.winner_team_id <> t.id)::int AS losses,
    COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END), 0)::int AS points_for,
    COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score ELSE m.home_score END), 0)::int AS points_against,
    (COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END), 0)
     - COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score ELSE m.home_score END), 0))::int AS point_diff,
    ROW_NUMBER() OVER (
        PARTITION BY d.id
        ORDER BY COUNT(*) FILTER (WHERE m.winner_team_id = t.id) DESC,
                 (COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END), 0)
                  - COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.away_score ELSE m.home_score END), 0)) DESC,
                 COALESCE(SUM(CASE WHEN m.home_team_id = t.id THEN m.home_score ELSE m.away_score END), 0) DESC
    )::int AS rank,
    now() AS computed_at
FROM divisions d
JOIN brackets b ON b.division_id = d.id
JOIN matches m ON m.bracket_id = b.id AND m.status = 'completed'
JOIN teams t ON t.id IN (m.home_team_id, m.away_team_id)
GROUP BY d.id, t.id;
SQL);
        DB::statement('CREATE UNIQUE INDEX mv_standings_division_team ON mv_standings (division_id, team_id)');
    }

    /**
     * Down.
     *
     * @return void Nothing.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_standings');
        }
    }
};
