<?php

namespace Database\Seeders;

use App\Enums\AccountRole;
use App\Models\Account;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $account = Account::factory()->for($owner, 'owner')->create([
            'name' => 'Freelance Studio',
        ]);

        $team = User::factory(2)->create();

        $account->users()->syncWithoutDetaching([
            $owner->id => ['role' => AccountRole::Owner],
            $team[0]->id => ['role' => AccountRole::Manager],
            $team[1]->id => ['role' => AccountRole::Member],
        ]);

        $clients = Client::factory()
            ->count(3)
            ->for($account)
            ->create();

        $projects = collect();

        foreach (range(1, 5) as $_) {
            $project = Project::factory()
                ->for($account)
                ->for($clients->random(), 'client')
                ->create();

            $project->assignees()->sync($team->random(2)->pluck('id')->all());
            $projects->push($project);
        }

        Task::factory()
            ->count(3)
            ->for($account)
            ->for($clients->random(), 'client')
            ->create([
                'project_id' => null,
                'assigned_user_id' => $team->random()->id,
            ]);

        foreach (range(1, 7) as $_) {
            $project = $projects->random();

            Task::factory()
                ->for($account)
                ->for($project->client, 'client')
                ->for($project)
                ->create([
                    'assigned_user_id' => $team->random()->id,
                ]);
        }
    }
}
