
<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
    <div class="container-fluid">
        <span class="navbar-text me-auto" ><?=lang(array('string'=>'Site Info' ))?></span>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
                <button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                <ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i><?=lang('Light')?></button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i><?=lang('Dark')?></button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i><?=lang('Auto')?></button></li>
                </ul>
            </li>
            <li class="nav-item">
                <button title="<?=lang(array('string'=>'Close' ))?>" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="Close">
                    <span class="bi bi-x-lg"></span>
                </button>
            </li>
        </ul>
    </div>
</nav>
<main class="container">
    <div class="site_info row row-cols-1 row-cols-md-2 g-4 my-4">
        <div class="col">
            <div class="card my-4 h-100">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    <?=lang('General')?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 my-2">
                            <dl>
                                <dt><?=lang('Hostname')?></dt>
                                <dd> <?=h(HOSTNAME_SETTING)?> </dd>
                                <dt><?=lang('Hosted')?></dt>
                                <dd> <?php if (we_host()): ?> <?=lang('Yes')?> <?php else: ?> <?=lang('No')?> <?php endif ?> </dd>
                                <dt><?=lang('Website IP Address')?></dt>
                                <dd> <?php if (isset($_SERVER['SERVER_ADDR']) ): ?><?=h($_SERVER['SERVER_ADDR'])?><?php else: ?><?=h($_SERVER['LOCAL_ADDR'])?><?php endif ?> </dd>
                                <dt><?=lang('Secure Mode')?></dt>
                                <dd> <?php if (URL_SCHEME == 'https://'): ?> <?=lang('On')?> <?php else: ?> <?=lang('Off')?> <?php endif ?> </dd>
                                <dt><?=lang('Version')?></dt>
                                <dd> <?=h(VERSION)?> <?=h(EDITION)?> </dd>
                                <dt><?=lang('Disk Usage')?> (<?=lang('Files')?>)</dt>
                                <dd> <?=h(convert_bytes_to_string($disk_usage, 2))?> </dd>
                                <dt><?=lang('Disk Usage')?> (<?=lang('Backups')?>)</dt>
                                <dd> <?=h(convert_bytes_to_string(folderSize('data/backups'), 2))?> </dd>
                                <dt><?=lang('Private Label')?></dt>
                                <dd> <?php if (PRIVATE_LABEL): ?> <?=lang('On')?> <?php else: ?> <?=lang('Off')?> <?php endif ?> </dd>
                                <dt><?=lang('Environment')?></dt>
                                <dd> <?php if (defined('ENVIRONMENT') and ENVIRONMENT == 'development'): ?> <?=lang('development')?> <?php else: ?> <?=lang('production')?> <?php endif ?> </dd>
                                <dt><?=lang('Email Campaign Job')?></dt>
                                <dd> <?php if (defined('EMAIL_CAMPAIGN_JOB') and EMAIL_CAMPAIGN_JOB): ?> <?=lang('On')?> <?php else: ?> <?=lang('Off')?> <?php endif ?> </dd>
                                
                            </dl>
                        </div>
                    </div>
                </div>
            </div> 
        </div>
        <div class="col">
            <div class="card my-4 h-100">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    <?=lang('Server')?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 my-2">
                           
                            <dl>
                                <dt><?=lang('PHP Version')?></dt>
                                <dd> <?=h(phpversion())?> </dd>
                                <dt><?=lang('MySQL Version')?></dt>
                                <dd> <?=h(db("SELECT VERSION()"))?> </dd>
                                <dt><?=lang('System')?></dt>
                                <dd> <?=h(php_uname())?> </dd>
                                <dt><?=lang('Operating System')?></dt>
                                <dd> 
                                    <?php if (defined('PHP_OS_FAMILY')): ?> 
                                        <?=h(PHP_OS_FAMILY)?> 
                                    <?php else:?> 
                                        <?=h(PHP_OS)?> 
                                    <?php endif; ?> 
                                </dd>
                                <dt><?=lang('Web Server')?></dt>
                                <dd> <?=h($_SERVER['SERVER_SOFTWARE'])?> </dd>
                                <dt><?=lang('Disabled Functions')?></dt>
                                <dd> <?=ini_get('disable_functions')?> </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div> 
        </div>
    </div>
    <div class="row my-4">
        <div class="col-12">
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    <?=lang('PHP')?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 my-2">
                            <iframe src="pi.php" class="bg-light rounded w-100" onload="resizeIframe(this)"></iframe>
                        </div>
                    </div>
                </div>
            </div> 
        </div> 
    </div>
</main>