<!DOCTYPE html>
<html lang="en"> 
<head>
    <title>XGPT - Dashboard</title>

    <!-- Meta -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <meta name="description" content="XGPT - Use Nightbot as AI chatbot powered by ChatGPT">
    <meta name="author" content="xgerhard">
    
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <!-- Plugins CSS -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.15.2/styles/atom-one-dark.min.css">
    <link rel="stylesheet" href="assets/plugins/simplelightbox/simple-lightbox.min.css">

    <!-- Theme CSS -->  
    <link id="theme-style" rel="stylesheet" href="assets/css/theme.css">

</head> 

<body class="docs-page">    
    <header class="header fixed-top">	    
        <div class="branding docs-branding">
            <div class="container-fluid position-relative py-2">
                <div class="docs-logo-wrapper">
                    <button id="docs-sidebar-toggler" class="docs-sidebar-toggler docs-sidebar-visible me-2 d-xl-none" type="button">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="site-logo"><a class="navbar-brand" href="/"><span class="logo-text">X<span class="text-alt">GPT</span></span></a></div>    
                </div><!--//docs-logo-wrapper-->
                <div class="docs-top-utilities d-flex justify-content-end align-items-center">	
                    <ul class="social-list list-inline mx-md-3 mx-lg-5 mb-0 d-none d-lg-flex">
                        <li class="list-inline-item"><a href="https://github.com/xgerhard/XGPT" target="blank"><i class="fa fa-github fa-fw"></i></a></li>
                        <li class="list-inline-item"><a href="mailto:hi@gerhard.dev" target="blank"><i class="fa fa-envelope fa-fw"></i></a></li>
                    </ul><!--//social-list-->
                    <a href="https://xgpt.gerhard.dev/logout" class="btn btn-secondary d-none d-lg-flex">Logout</a>
                </div><!--//docs-top-utilities-->
            </div><!--//container-->
        </div><!--//branding-->
    </header><!--//header-->
    
    
    <div class="docs-wrapper">
        <div id="docs-sidebar" class="docs-sidebar">
            <nav id="docs-nav" class="docs-nav navbar">
                <ul class="section-items list-unstyled nav flex-column pb-3">
                    <li class="nav-item section-title"><a class="nav-link scrollto active" href="#section-1"><span class="theme-icon-holder me-2"><i class="fa fa-map-signs"></i></span>Home</a></li>
                    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#section-2"><span class="theme-icon-holder me-2"><i class="fa fa-arrow-down"></i></span>Installation</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-2-1">Commands</a></li>
                    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#section-3"><span class="theme-icon-holder me-2"><i class="fa fa-gear"></i></span>Settings</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-3-0">OpenAI API key</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-3-1">AI instructions</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-3-2">Final AI instructions</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-3-3">Conversation ID</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-3-4">Mention user</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-3-5">Sponsor heart</a></li>
                    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#section-4"><span class="theme-icon-holder me-2"><i class="fa fa-lightbulb-o"></i></span>Help</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-4-1">Contact</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#item-4-2">Contribute</a></li>
                </ul>
            </nav><!--//docs-nav-->
        </div><!--//docs-sidebar-->
        <div class="docs-content">
            <div class="container">
                @if(Session::has('message'))
                <div class="callout-block {{ Session::get('alert-class', 'callout-block-info') }} me-1">
                    <div class="content">
                        <p>{{ Session::get('message') }}</p>
                    </div>
                </div>
                @endif

                <article class="docs-article" id="section-1">
                    <header class="docs-header">
                        <h1 class="docs-heading">Hiya {{ auth()->user()->name }}! </h1>
                        <h2></h2>
                        <section class="docs-intro">
                            <p>Welcome to your XGPT dashboard, from here you can access your settings and/or install our Nightbot command.</p>
                        </section><!--//docs-intro-->
                    </header>
                </article>

                <article class="docs-article" id="section-2">
                    <header class="docs-header">
                        <h1 class="docs-heading">Installation</h1>
                        <section class="docs-intro">
                            <p></p>
                        </section><!--//docs-intro-->
                    </header>
                    <section class="docs-section" id="item-2-1">
                        <h2 class="section-heading">Commands</h2>

                        @if (empty($commands))
                        <div class="callout-block callout-block-danger me-1">
                            <div class="content">
                                <h4 class="callout-title">
                                    <span class="callout-icon-holder">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span><!--//icon-holder-->
                                    Woops!
                                </h4>
                                <p>Looks like you haven't installed our Nightbot command yet, let us do it for you.</p>
                            </div><!--//content-->
                        </div>
                        <p>
                            <form action="" method="POST">
                                @csrf <!-- {{ csrf_field() }} -->
                                <input type="hidden" name="action" value="install_command"/>
                                Command code: <input type="text" name="command_code" value="!ai" required/>
                                <button type="submit" class="btn btn-secondary">Install command</button>
                            </form>
                        </p>
                        <p><b>Note:</b> existing command with this command code will be overwritten.</p>
                        @else
                        <div class="callout-block callout-block-success">
                            <div class="content">
                                <h4 class="callout-title">
                                    <span class="callout-icon-holder me-1">
                                        <i class="fa fa-thumbs-up"></i> 
                                    </span><!--//icon-holder-->
                                    Awesome!
                                </h4>
                                <p>Looks like you have our Nightbot command installed.</p>
                            </div><!--//content-->
                        </div>
                        @if($command_needs_token)
                        <div class="callout-block callout-block-danger me-1">
                            <div class="content">
                                <h4 class="callout-title">
                                    <span class="callout-icon-holder">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </span><!--//icon-holder-->
                                    Woops!
                                </h4>
                                <p>Since you're using your own OpenAI API key, we need to at a personal token to the command. This is for security reasons, so only your channel will be able to use your API key.</p>
                            </div><!--//content-->
                        </div>
                        <p>
                            <form action="" method="POST">
                                @csrf <!-- {{ csrf_field() }} -->
                                <input type="hidden" name="action" value="update_commands"/>
                                <button type="submit" class="btn btn-secondary">Update commands</button>
                            </form>
                        </p>
                        @endif
                        <div class="table-responsive my-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th scope="col">Command code</th>
                                        <th scope="col">Userlevel</th>
                                        <th scope="col">Cooldown</th>
                                        <th scope="col">Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($commands as $command)
                                    <tr>
                                        <th>{{ $command['name'] }}</th>
                                        <td>{{ $command['userLevel'] }}</td>
                                        <td>{{ $command['coolDown'] }}</td>
                                        <td>{{ $command['message'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p><b>Note:</b> Please be carefull with sharing this command to friends, it contains a personal token for security purposes. Have you friends sign in <a href="https://xgpt.gerhard.dev/dashboard" target="blank">to this dashboard</a> to install their XGPT command.</p>
                        @endif
                    </section><!--//section-->
                </article><!--//docs-article-->

                <article class="docs-article" id="section-3">
                    <form action="" method="POST" id="settings">
                        @csrf
                        <header class="docs-header">
                            <h1 class="docs-heading">Settings</h1>
                            <section class="docs-intro">
                                <p>Manage your channels settings here. These settings only effect your channel, these settings have no effect if you use our command in other channels.</p>
                            </section><!--//docs-intro-->
                        </header>
                        <section class="docs-section" id="item-3-0">
                            <h2 class="section-heading">OpenAI API key</h2>
                            <p>We highly suggest using your own API key. Benefits:
                                <ul>
                                    <li>The command will always work, no need to worry about monthly limits.</li>
                                    <li>Reduce our costs, which helps to keep running this app for free! 😊</li>
                                    <li>You'll receive a heart icon in front of your name when using the command as a thank you! ❤️</li>
                                </ul>
                            </p>
                            <p>
                                Sign up for your own API key here: <a href="https://platform.openai.com/" target="blank">platform.openai.com</a><br/>
                                After creating your account and setting up your billing information, you can create an API key here: <a href="https://platform.openai.com/api-keys" target="blank">platform.openai.com/api-keys</a>.
                            </p>
                            <p>
                                More information about pricing here: <a href="https://openai.com/pricing" target="blank">openai.com/pricing</a>. We use the gpt-3.5-turbo model which currectly costs $0.002 / 1K tokens. Tokens are calculated based on the amount of text that has to be processed. A helpful rule of thumb is that one token generally corresponds to ~4 characters of text for common English text. This translates to roughly ¾ of a word (so 100 tokens ~= 75 words).<br/>
                            </p>
                            <p>
                                From our experience so far, the costs are about $0.01 every time a command is used.	
                            </p>
                            <div class="form-group">
                                <label for="api_key">OpenAI API key</label>
                                <input type="password" class="form-control" id="api_key" name="api_key" value="@if( auth()->user()->settings ){{ auth()->user()->settings->api_key}}@endif"/>
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="item-3-1">
                            <h2 class="section-heading">AI instructions</h2>
                            <p>These instructions will be provided to the AI at the start of every chat, so the AI knows what it is suppose to be doing. The order is: AI instructions -> the viewer message -> final AI instructions. You can provide various instructions, in your desired language. You can also provide extra information about your channel, so the AI can use this info for better answers.<br/>A few examples:
                                <ul>
                                    <li>You are a helpful assistant for a Twitch chat.</li>
                                    <li>You are an assistant for a Twitch chat where viewers can ask you questions, be snappy, try to roast the viewer.</li>
                                    <li>You are an assistant for a Twitch chat, reply with an Texan accent.</li>
                                    <li>You are an assistant for a Twitch chat, UwU'fy your replies, include emojis at the end of each message.</li>
                                    <li>You are an assisant for the Twitch channel xgerhard, reply in dutch <i>(This instruction could be provided in dutch)</i></li>
                                </ul>
                            </p>
                            <div class="form-group">
                                <label for="start_instructions">AI instructions</label>
                                <textarea class="form-control" id="start_instructions" name="start_instructions" style="height:100px" maxlength="500">@if( auth()->user()->settings ){{ auth()->user()->settings->start_instructions}}@endif</textarea>
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="item-3-2">
                            <h2 class="section-heading">Final AI instructions</h2>
                            <p>This is the final instruction we send to the AI, the order is: AI instructions -> the viewer message -> final AI instructions. Currently we use this to ask to reply within a certain amount of characters (for Twitch 250, for Youtube 150). You can play around with this number, or maybe give instruction to reply in a certain language. This instruction just like the first one doesn't have to be in English, can be in your desired language. Or you could leave this settings empty.<br/>A few examples:
                                <ul>
                                    <li>Default instructions: Answer in 250 characters or less.</li>
                                    <li>Answer in 250 characters or less and in dutch. <i>(This instruction could be provided in dutch)</i></li>
                                </ul>
                            </p>
                            <div class="form-group">
                                <label for="end_instructions">Final AI instructions</label>
                                <textarea class="form-control" id="end_instructions" name="end_instructions" style="height:100px" maxlength="500">@if( auth()->user()->settings ){{ auth()->user()->settings->end_instructions}}@endif</textarea>
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="item-3-3">
                            <h2 class="section-heading">Conversation ID</h2>
                            <p>At the end of each message is a hashtag with a code, for example #abc. This code can be used to continue a conversation, for example to use earlier info in your next question. View examples: <a href="https://xgpt.gerhard.dev/#item-1-2" target="blank">here</a>.<br/>
                                While this feature is convenient, most of the time it's not used. Most users just ask one thing at the time and don't have a full conversations with the bot.<br/>
                                By disabling this setting, the hashtags will be removed from the message, freeing up space for a few extra characters of other info.  
                            </p>
                            <div class="form-group">
                                <input type="checkbox" @if( auth()->user()->settings and auth()->user()->settings->show_conversation_id) checked="checked" @endif name="show_conversation_id" id="show_conversation_id"/> Show conversation ID/hashtag at the end of the message
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="item-3-4">
                            <h2 class="section-heading">Mention user</h2>
                            <p>At the start of each message we @mention the user who called the command.<br/>
                            By disabling this setting, the username of the viewer will be removed from the message, freeing up space for a few extra characters of other info.
                            </p>
                            <div class="form-group">
                                <input type="checkbox" @if( auth()->user()->settings and auth()->user()->settings->mention_user) checked="checked" @endif name="mention_user" id="mention_user"/> Mention user at the start of the message
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="item-3-5">
                            <h2 class="section-heading">Sponsor heart</h2>
                            <p>Are you a sponsor? Awesome! As appreciation we show a ❤️ emoji in front of your username when you use our app.<br/>
                            Want to become a sponsor, head over to: <a href="https://github.com/sponsors/xgerhard" target="blank">github.com/sponsors/xgerhard</a>.<br/> 
                            By disabling this setting, the emoji won't be shown in front of your username.
                            </p>
                            <div class="form-group">
                                <input type="checkbox" @if( auth()->user()->settings and auth()->user()->settings->show_sponsor_heart) checked="checked" @endif name="show_sponsor_heart" id="show_sponsor_heart"/> Show sponsor ❤️ emoji
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="item-3-6">
                            <h2 class="section-heading">Save settings</h2>
                            <p><button type="submit" id="save_settings" name="save_settings" class="btn btn-primary">Save settings</button><p>
                        </section>
                    </form>
                </article><!--//docs-article-->

                <article class="docs-article" id="section-4">
                    <header class="docs-header">
                        <h1 class="docs-heading">Help</h1>
                        <section class="docs-intro">
                            <p></p>
                        </section><!--//docs-intro-->
                    </header>
                     <section class="docs-section" id="item-4-1">
                        <h2 class="section-heading">Contact</h2>
                        <p>Need help with the installation, any questions, or a thank you, feel free to send me a mail at: <a href="mailto:hi@gerhard.dev" target="blank">hi@gerhard.dev</a>.</p>
                    </section><!--//section-->
                    <section class="docs-section" id="item-4-2">
                        <h2 class="section-heading">Contribute</h2>
                        <p>The source code is viewable for everyone, have suggestions, improvements, or just curious? View this app on GitHub: <a href="https://github.com/xgerhard/XGPT" target="blank">GitHub.com/xgerhard/XGPT</a>.</p>
                    </section><!--//section-->
                </article><!--//docs-article-->
                <footer class="footer">
                    <div class="container text-center py-5">
                        <!--/* This template is free as long as you keep the footer attribution link. If you'd like to use the template without the attribution link, you can buy the commercial license via our website: themes.3rdwavemedia.com Thank you for your support. :) */-->
                        <small class="copyright">Created with <span class="sr-only">love</span><i class="fa fa-heart" style="color: #fb866a;"></i> by <a class="theme-link" href="https://gerhard.dev" target="blank">Gerhard</a> and designed by <a class="theme-link" href="http://themes.3rdwavemedia.com" target="_blank">Xiaoying Riley</a>.</small>
                        <ul class="social-list list-unstyled pt-4 mb-0">
                            <li class="list-inline-item"><a href="https://github.com/xgerhard/XGPT" target="blank"><i class="fa fa-github fa-fw"></i></a></li> 
                            <li class="list-inline-item"><a href="mailto:hi@gerhard.dev" target="blank"><i class="fa fa-envelope fa-fw"></i></a></li>
                        </ul><!--//social-list-->
                    </div>
                </footer>
            </div> 
        </div>
    </div><!--//docs-wrapper-->

    <!-- Javascript -->          
    <script src="assets/plugins/popper.min.js"></script>
    <script src="assets/plugins/bootstrap/js/bootstrap.min.js"></script>  

    <!-- Page Specific JS -->
    <script src="assets/plugins/smoothscroll.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.15.8/highlight.min.js"></script>
    <script src="assets/js/highlight-custom.js"></script> 
    <script src="assets/plugins/simplelightbox/simple-lightbox.min.js"></script>      
    <script src="assets/plugins/gumshoe/gumshoe.polyfills.min.js"></script> 
    <script src="assets/js/docs.js"></script> 

</body>
</html> 

