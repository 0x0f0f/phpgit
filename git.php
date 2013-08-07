<?php


class Git 
{

    protected static $bin = '/usr/local/bin/git';

    public static function &create($repo_path, $source = null) 
    {
        return GitImpl::create_new($repo_path, $source);
    }

    public static function open($repo_path,$create_new = true)
    {
        return new GitImpl($repo_path,$create_new);
    }

    public static function delete($repo)
    {
        exec("rm -rf $repo");
    }
}

class GitImpl
{

    protected $repo_path = null;

    public static function &create_new($repo_path, $source = null)
    {
        if (is_dir($repo_path) && file_exists($repo_path."/.git") && is_dir($repo_path."/.git")) {
            throw new Exception('"'.$repo_path.'" is already a git repository');
        } else {
            $repo = new self($repo_path, true, false);
            if (is_string($source)) {
                $repo->clone_from($source);
            } else {
                $repo->run(' --bare init');
            }
            return $repo;
        }
    }

    public function __construct($repo_path = null, $create_new = false)
    {
        if (is_string($repo_path)) 
        {
            $this->set_repo_path($repo_path, $create_new);
        }
    }

    public function set_repo_path($repo_path, $create_new = false)
    {
        if (is_string($repo_path))
        {
            if ($new_path = realpath($repo_path))
            {
                $repo_path = $new_path;
                if (is_dir($repo_path)) {
                    if (file_exists($repo_path."/.git") && is_dir($repo_path."/.git")) {
                        $this->repo_path = $repo_path;
                    }
                    else {
                        if ($create_new) {
                            $this->repo_path = $repo_path;
                                $this->run(' --bare init');
                        }
                        else {
                            throw new Exception('"'.$repo_path.'" is not a git repository');
                        }
                    }
                } else {
                    throw new Exception('"'.$repo_path.'" is not a directory');
                }
            }
            else {
                if ($create_new)
                {
                    if ($parent = realpath(dirname($repo_path))) {
                        mkdir($repo_path);
                        $this->repo_path = $repo_path;
                         $this->run(' --bare init');
                    } else {
                        throw new Exception('cannot create repository in non-existent directory');
                    }
                }
                else {
                    throw new Exception('"'.$repo_path.'" does not exist');
                }
            }
        }
    }

    protected function run_command($command) 
    {
        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = array();
        $resource = proc_open($command, $descriptorspec, $pipes, $this->repo_path);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $status = trim(proc_close($resource));
        if ($status) throw new Exception($stderr);

        return $stdout;
    }

    public function run($command) 
    {
        return $this->run_command(Git::get_bin()." ".$command);
    }

    public function add($files = "*") 
    {
        if (is_array($files)) {
            $files = '"'.implode('" "', $files).'"';
        }
        return $this->run("add $files -v");
    }

    public function commit($message = "")
    {
        return $this->run("commit -av -m ".escapeshellarg($message));
    }

    public function clone_to($target)
    {
        return $this->run("clone --local ".$this->repo_path." $target");
    }

    public function clone_from($source)
    {
        return $this->run("clone --local $source ".$this->repo_path);
    }

    // roll back to some tag
    public function rollback($tag)
    {
        return $this->run("checkout $tag");
    }


    public function merge($branch)
    {
        return $this->run("merge $branch --no-ff");
    }


    public function add_tag($tag, $message = null)
    {
        if ($message === null) {
            $message = $tag;
        }
        return $this->run("tag -a $tag -m $message");
    }


    public function list_tags()
    {
        $tags = explode("\n",$this->run(" tag"));
        return $tags;
    } 

    public function push($remote, $branch)
    {
        return $this->run("push --tags $remote $branch");
    }

    public function pull($remote, $branch)
    {
        return $this->run("pull $remote $branch");
    }
}

