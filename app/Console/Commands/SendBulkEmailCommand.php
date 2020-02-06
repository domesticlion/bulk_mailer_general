<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\App;
use Illuminate\Console\Command;
use \App\ProcessedQueue;
use Illuminate\Support\Facades\Mail;

class SendBulkEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send_bulk_email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description.';

    /**
     * Create a new command instance.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $sqs = App::make('aws')->createClient('sqs');
        for ($i = 1; $i <= 100; $i++) {
            try {
                $result = $sqs->receiveMessage([
                    'QueueUrl' => config('bulkmailer.queue'),
                    'MaxNumberOfMessages' => 1
                ]);
            } catch (\Exception $e) {
                exit;
            }
            if ($messages = $result->getPath('Messages')) {
                foreach ($messages as $message) {
                    if (!$r = ProcessedQueue::where('queue_id', $message['MessageId'])->count()) {
                        $data = json_decode($message['Body']);
                        if (!empty($data->from) && !empty($data->to)) {
                            ProcessedQueue::create(['queue_id' => $message['MessageId']]);
                            $type = !empty($data->htmlBody) ? ['emails.html', 'emails.text'] : ['text' => 'emails.text'];
                            Mail::send($type, ['data' => $data], function ($m) use ($data) {
                                $m->from($data->from, (!empty($data->from_name) ? $data->from_name : null))
                                    ->to($data->to, (!empty($data->to_name) ? $data->to_name : null))
                                    ->subject(!empty($data->subject) ? $data->subject : null);
                            });
                        }
                    }
                    $sqs->deleteMessage([
                        'QueueUrl' => config('bulkmailer.sqs.queue'),
                        'ReceiptHandle' => $message['ReceiptHandle'],
                    ]);
                }
            }
        }
    }
}
