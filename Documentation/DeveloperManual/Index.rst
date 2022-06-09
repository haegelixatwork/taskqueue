.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _dev-manual:

Developer Manual
================

This chapter explains how a developer can use this extension.

Extending a task
----------------

Fist of all create an extension that extends the task model. Lets call our new model **MailTask**.

MailTask:

::

   class MailTask extends \Undkonsorten\Taskqueue\Domain\Model\Task{
   ...
   }


ext_typoscript_setup.txt:

::

   config.tx_extbase {
    persistence {
        classes {
         Undkonsorten\Wall\Domain\Model\MailTask {
            mapping {
               tableName = tx_taskqueue_domain_model_task
               recordType = tx_wall_domain_model_task
            }
         }
         Undkonsorten\Taskqueue\Domain\Model\Task {
            subclasses {
                  tx_wall_domain_model_task = Undkonsorten\Wall\Domain\Model\MailTask
               }
         }
      }
   }

You can also add your own setter and getter here. But to make the date accessible for the taskqueue extension all data needs to be stored in the
data array via the set/getProperty.

We add an user, a post, a comment and a mail to the task:

::

   /**
    *
    * @param integer $user
    */
   public function setUser($user) {
      $this->setProperty('user', $user);
   }

   /**
    *
    * @return integer
    */
   public function getUser() {
      return $this->getProperty('user');
   }

   /**
    *
    * @return integer
    */
   public function getPost() {
      return $this->getProperty('post');
   }
   /**
    *
    * @param integer $post
    */
   public function setPost($post) {
      $this->setProperty('post', $post);
   }
   /**
    *
    * @return integer
    */
   public function getComment() {
      return $this->getProperty('comment');
   }

   /**
    *
    * @param integer $comment
    */
   public function setComment($comment) {
      $this->setProperty('comment', $comment);
   }

   /**
    *
    * @param array $mail
    */
   public function setMail($mail) {
      $this->setProperty('mail', $mail);
   }

   /**
    *
    * @return array
    */
   public function getMail() {
      return $this->getProperty('mail');
   }

As you see we don't use protected variables here.

Now you have your own task that you can add to the task queue. But before you have to implement the run() method.

Implementing run()
------------------
Every task has its own run method(). In this method you have to define the actual work that needs to be done. The taskqueue extension does not
know anything about what the task is doing, it just calls the run() method on every task.

This might be an example:

::

   public function run(){
      $this->setStatus(\Undkonsorten\Taskqueue\Domain\Model\TaskInterface::RUNNING);

      $mailVariables = array();
      $mailVariables['user'] = $this->userRepository->findByUid($this->getUser());
      $mailVariables['post'] = $this->postRepository->findByUid($this->getPost());
      $mailVariables['comment'] = $this->commentRepository->findByUid($this->getComment());

      //Update the mail
      $mail = $this->getMail();
      if($mailVariables['user']->getFirstName() && $mailVariables['user']->getLastName()){
            $mail['receiverName'] = $mailVariables['user']->getFirstName()." ".$mailVariables['user']->getLastName();
         }else{
            $mail['receiverName'] = $mailVariables['user']->getUsername();
         }

      $mail['receiverEmail'] = $mailVariables['user']->getEmail();
      $this->setMail($mail);
      try {
         $this->mailManager->sendTemplateEmail($this->getMail(), $mailVariables);
         $this->setMessage(LocalizationUtility::translate(
               'tx_wall_task.notification.send',
               'wall',
               array('1' =>$mailVariables['user']->getUsername())
         ));
         $this->setStatus(\Undkonsorten\Taskqueue\Domain\Model\TaskInterface::FINISHED);
      } catch (\Exception $exception) {
         $this->setMessage($exception->getMessage());
         $this->setStatus(\Undkonsorten\Taskqueue\Domain\Model\TaskInterface::FAILED);
      }
   }

Here the run method makes use of the data stored in the task. With this data a mail is generated and then send.

Also the status of the task is controlled here. As you can see.

Adding a task to the taskqueue
------------------------------

After you have a ready implemented task you need to add it to the queue so that it can be executed.

::

   /**
    * task repository
    * @var \Undkonsorten\Taskqueue\Domain\Repository\TaskRepository
    *
    * @inject
    */
   protected $taskRepository;

   ...

   /*@var $task Undkonsorten\Wall\Domain\Model\MailTask */
   $task = $this->objectManager->get('Undkonsorten\Wall\Domain\Model\MailTask');

   $task->setPost($newPost->getUid());
   $task->setCommend($newComment->getUid());
   $task->setMail($mail);
   $task->setUser($user->getUid());
   $task->setStartDate(time());
   $this->taskRepository->add($task);

   ...


- First you need to inject the task repository.
- The you get an task object via the object manager (keep in mind to get you own task object, the one from taskqueue is abstract)
- Then you set the needed data.

What is important here is, that it would be good to store only uids in the task object, because these data get serialized and stored in the database.
When you store complete objects the performance will go down.

Keep in mind that when you create new objects, for example a post, these objects don't have an uid until they get stored in the database.
To it might me useful to run a peristAll before add an uid to the task.

::

   ...
   // we need to persist the new object because otherwise it would not have an uid
   // and we want only uids to be saved in task
   $this->persitenceManager->persistAll();
   $task->setPost($newPost->getUid());
   ...


Concrete Example
----------------

All the code examples are taken from the extension **wall**. This extension allows feusers to write posts and comment on a wall, a little bit
like facebook. It uses **taskqueue** to notify users on new posts and comments.
The extension is available here: http://typo3.org/extensions/repository/view/wall

It might help you to have a look at the code.


Stop all task of certain name
-----------------------------

Sometimes it is necessary to skip all tasks if a specific error has happened. For example if you run 15 tasks in one run
and they are working on an api, if the first task gets an timeout if might be usefull to skip the other tasks.

To achieve this you need to throw a StopRunException, which ships with the extension.

StopRunException:

::

   namespace Undkonsorten\Taskqueue\Exception;

   class StopRunException extends Exception
   {
       /**
        * @var string
        */
       protected $taskname;

       public function __construct($message = "", $code = 0, Throwable $previous = null, string $taskname)
       {
           $this->taskname = $taskname;
           parent::__construct($message, $code, $previous);
       }

       /**
        * @return string
        */
       public function getTaskname(): string
       {
           return $this->taskname;
       }

       /**
        * @param string $taskname
        */
       public function setTaskname(string $taskname): void
       {
           $this->taskname = $taskname;
       }

   }

If you can use the task name to only skip certain tasks.

Example:

::

   class MotionTask
   {
      public function run(): void
      {
      ...
       try {
            //Something bad happens here
        }catch(ConnectException $exception){
            throw new StopRunException("Consolidate API was not reachable.", "1637171378", $exception , MotionTask::class);
        }
      ...
      }
