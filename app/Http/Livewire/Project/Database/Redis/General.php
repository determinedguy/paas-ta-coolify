<?php

namespace App\Http\Livewire\Project\Database\Redis;

use App\Actions\Database\StartDatabaseProxy;
use App\Actions\Database\StopDatabaseProxy;
use App\Models\StandaloneRedis;
use Exception;
use Livewire\Component;

class General extends Component
{
    protected $listeners = ['refresh'];

    public StandaloneRedis $database;
    public ?string $db_url = null;
    public ?string $db_url_public = null;

    protected $rules = [
        'database.name' => 'required',
        'database.description' => 'nullable',
        'database.redis_conf' => 'nullable',
        'database.redis_password' => 'required',
        'database.image' => 'required',
        'database.ports_mappings' => 'nullable',
        'database.is_public' => 'nullable|boolean',
        'database.public_port' => 'nullable|integer',
        'database.is_log_drain_enabled' => 'nullable|boolean',
    ];
    protected $validationAttributes = [
        'database.name' => 'Name',
        'database.description' => 'Description',
        'database.redis_conf' => 'Redis Configuration',
        'database.redis_password' => 'Redis Password',
        'database.image' => 'Image',
        'database.ports_mappings' => 'Port Mapping',
        'database.is_public' => 'Is Public',
        'database.public_port' => 'Public Port',
    ];
    public function mount()
    {
        $this->db_url = $this->database->getDbUrl(true);
        if ($this->database->is_public) {
            $this->db_url_public = $this->database->getDbUrl();
        }
    }
    public function instantSaveAdvanced() {
        try {
            if (!$this->database->destination->server->isLogDrainEnabled()) {
                $this->database->is_log_drain_enabled = false;
                $this->emit('error', 'Log drain is not enabled on the server. Please enable it first.');
                return;
            }
            $this->database->save();
            $this->emit('success', 'Database updated successfully.');
            $this->emit('success', 'You need to restart the service for the changes to take effect.');
        } catch (Exception $e) {
            return handleError($e, $this);
        }
    }
    public function submit()
    {
        try {
            $this->validate();
            if ($this->database->redis_conf === "") {
                $this->database->redis_conf = null;
            }
            $this->database->save();
            $this->emit('success', 'Database updated successfully.');
        } catch (Exception $e) {
            return handleError($e, $this);
        }
    }
    public function instantSave()
    {
        try {
            if ($this->database->is_public && !$this->database->public_port) {
                $this->emit('error', 'Public port is required.');
                $this->database->is_public = false;
                return;
            }
            if ($this->database->is_public) {
                if (!str($this->database->status)->startsWith('running')) {
                    $this->emit('error', 'Database must be started to be publicly accessible.');
                    $this->database->is_public = false;
                    return;
                }
                StartDatabaseProxy::run($this->database);
                $this->db_url_public = $this->database->getDbUrl();
                $this->emit('success', 'Database is now publicly accessible.');
            } else {
                StopDatabaseProxy::run($this->database);
                $this->db_url_public = null;
                $this->emit('success', 'Database is no longer publicly accessible.');
            }
            $this->database->save();
        } catch (\Throwable $e) {
            $this->database->is_public = !$this->database->is_public;
            return handleError($e, $this);
        }
    }
    public function refresh(): void
    {
        $this->database->refresh();
    }

    public function render()
    {
        return view('livewire.project.database.redis.general');
    }
}
