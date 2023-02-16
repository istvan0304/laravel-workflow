# Laravel workflow

### <u>Installion</u>

composer require istvan0304/laravel-workflow

### <u>Configuration</u>

#### Attach Workflow behavior into your Model and set a workflow class attribute.

````
use Istvan0304\Workflow\WorkflowTrait;

class BlogPost extends Model
{
    use WorkflowTrait;

    /**
     * @var string
     */
    protected string $workflowClass = BlogPostWorkflow::class;
    
    private $workflowStatusAttribute = 'status';    // Default is status
````

Add observer to the EventServiceProvider:

````
protected $observers = [
        BlogPost::class => [\Istvan0304\Workflow\Observers\WorkflowObserver::class],
    ];
````

### <u>Create a workflow</u>

A workflow is defined as a PHP class that implements the ```` Istvan0304\Workflow\WorkflowDefinition ```` interface 
which declares three functions: 
>- statusLabels()       // This method must return an array representing the workflow status names.
>- statusActionLabels()       // This method must return an array representing the workflow status action names.
>- getDefinition()       // This method must return an array representing the workflow definition.

Example workflow class:

````
namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Istvan0304\Workflow\WorkflowDefinition;

class BlogPostWorkflow implements WorkflowDefinition
{
    const DRAFT = 'draft';
    const DELETED = 'deleted';
    const FINAL = 'final';
    const REJECT = 'reject';
    const FIX = 'fix';
    const PUBLISHED = 'published';

    /**
     * @return string[]
     */
    public static function statusLabels(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::DELETED => 'Deleted',
            self::FINAL => 'Finalized',
            self::REJECT => 'Rejected',
            self::FIX => 'Returned for repair',
            self::PUBLISHED => 'Published'
        ];
    }

    /**
     * @return string[]
     */
    public static function statusActionLabels(): array
    {
        return [
            self::DRAFT => 'Draft',
            self::DELETED => 'Delete',
            self::FINAL => 'Finalization',
            self::REJECT => 'Rejection',
            self::FIX => 'Return for repair',
            self::PUBLISHED => 'Publication'
        ];
    }

    /**
     * @return array
     */
    public static function getDefinition(): array
    {
        return [
            'initialStatus' => self::DRAFT,
            'status' => [
                self::DRAFT => [
                    'transition' => [self::FINAL, self::DELETED],
//                    'transition' => function(){
//                        return [self::REJECT, self::FIX];
//                    },
                ],
                self::DELETED => [
                    'transition' => [],
                ],
                self::FINAL => [
                    'transition' => (Auth::user()->hasRole('admin') ? [self::DELETED, self::REJECT, self::FIX, self::PUBLISHED] : [self::REJECT, self::FIX, self::PUBLISHED]),
                ],
                self::REJECT => [
                    'transition' => [],
                ],
                self::FIX => [
                    'transition' => [self::FINAL],
                ],
                self::PUBLISHED => [
                    'transition' => [],
                ]
            ]
        ];
    }
}
````

### <u>Start workflow</u>

````
/**
     * Create blog post
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     */
    public function createStore(Request $request)
    {
        $model = new BlogPost();
        
        // ...

        $model->start();     // Workflow start
        $model->fill($attributes);

        if ($model->save()) {
            return redirect('/blog-posts')->with('message', 'Successfully saved!');
        }
    }
````

### <u>Change status</u>

````
/**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|void
     */
    public function updateStore(Request $request, $id)
    {
        $model = BlogPost::find($id);
        
        // ...

        if($request->final){
            $model->sendToStatus(BlogPostWorkflow::FINAL);  // Change status
        }

        // ...
    }
````

If no transition between two status you are going to get an Exception.

### <u>License</u>

The MIT License (MIT). Please see License File for more information.