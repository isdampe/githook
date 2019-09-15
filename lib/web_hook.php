<?php defined("GITHOOK_VERSION") || exit;

class WebHook {
	private $token;
	private $repository;
	private $repo_path;
	private $branch;
	private $algo;

	public function __construct(string $token, string $repository,
		string $repo_path, string $branch) {

		$this->token = $token;
		$this->repository = $repository;
		$this->repo_path = $repo_path;
		$this->branch = $branch;


		define("TOKEN", $token);                                       // The secret token to add as a GitHub or GitLab secret, or otherwise as https://www.example.com/?token=secret-token
		define("REMOTE_REPOSITORY", $repository); // The SSH URL to your repository
		define("DIR", $repo_path);                          // The path to your repostiroy; this must begin with a forward slash (/)
		define("BRANCH", $branch);                                 // The branch route
		define("LOGFILE", "deploy.log");                                       // The name of the file you want to log to.
		define("GIT", "git");                                         // The path to the git executable
		define("MAX_EXECUTION_TIME", 180);                                     // Override for PHP's max_execution_time (may need set in php.ini)
		define("BEFORE_PULL", "");                                             // A command to execute before pulling
		define("AFTER_PULL", "");                                              // A command to execute after successfully pulling
	}

	public function execute(): bool {
		require(dirname(__FILE__) . "/../git-deploy.php");
		return true;
	}
}