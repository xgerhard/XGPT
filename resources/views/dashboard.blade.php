<!DOCTYPE html>
<html lang="en"> 
<head>
    <title>XGPT - Dashboard</title>

    <!-- Meta -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <meta name="description" content="XGPT - An AI chatbot for your stream chat, with Nightbot or Fossabot">
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
                    <a href="/logout" class="btn btn-secondary d-none d-lg-flex">Logout</a>
                </div><!--//docs-top-utilities-->
            </div><!--//container-->
        </div><!--//branding-->
    </header><!--//header-->
    
    
    <div class="docs-wrapper">
        <div id="docs-sidebar" class="docs-sidebar">
            <nav id="docs-nav" class="docs-nav navbar">
                <ul class="section-items list-unstyled nav flex-column pb-3">
                    <li class="nav-item section-title"><a class="nav-link scrollto active" href="#welcome"><span class="theme-icon-holder me-2"><i class="fa fa-map-signs"></i></span>Home</a></li>
                    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#installation"><span class="theme-icon-holder me-2"><i class="fa fa-arrow-down"></i></span>Installation</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#commands">Commands</a></li>
                    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#settings"><span class="theme-icon-holder me-2"><i class="fa fa-gear"></i></span>Settings</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#ai-instructions">AI instructions</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#conversation-id">Conversation ID</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#mention-user">Mention user</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#sponsor-heart">Sponsor heart</a></li>
                    <li class="nav-item section-title mt-3"><a class="nav-link scrollto" href="#help"><span class="theme-icon-holder me-2"><i class="fa fa-lightbulb-o"></i></span>Help</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#contact">Contact</a></li>
                    <li class="nav-item"><a class="nav-link scrollto" href="#contribute">Contribute</a></li>
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

                <article class="docs-article" id="welcome">
                    <header class="docs-header">
                        <h1 class="docs-heading">Hiya {{ auth()->user()->display_name }}! </h1>
                        <h2></h2>
                        <section class="docs-intro">
                            <p>Welcome to your XGPT dashboard, here you can change how the AI answers in your chat.</p>
                        </section><!--//docs-intro-->
                    </header>
                </article>

                <article class="docs-article" id="installation">
                    <header class="docs-header">
                        <h1 class="docs-heading">Installation</h1>
                        <section class="docs-intro">
                            <p></p>
                        </section><!--//docs-intro-->
                    </header>
                    <section class="docs-section" id="commands">
                        <h2 class="section-heading">Commands</h2>
                        @include('partials.commands')
                    </section><!--//section-->
                </article><!--//docs-article-->

                <article class="docs-article" id="settings">
                    <form action="" method="POST">
                        @csrf
                        <header class="docs-header">
                            <h1 class="docs-heading">Settings</h1>
                            <section class="docs-intro">
                                <p>Manage your channels settings here. These settings only affect your channel.</p>
                            </section><!--//docs-intro-->
                        </header>
                        <section class="docs-section" id="ai-instructions">
                            <h2 class="section-heading">AI instructions</h2>
                            <p>These instructions will be provided to the AI at the start of every chat, so the AI knows what it is supposed to do. You can provide various instructions, in your desired language. You can also provide extra information about your channel, so the AI can use this info for better answers.<br/>A few examples:
                                <ul>
                                    <li>You are a helpful assistant for my stream chat.</li>
                                    <li>You are an assistant for my stream chat where viewers can ask you questions, be snappy, try to roast the viewer.</li>
                                    <li>You are an assistant for my stream chat, reply with a Texan accent.</li>
                                    <li>You are an assistant for my stream chat, UwU'fy your replies, include emojis at the end of each message.</li>
                                    <li>You are an assistant for the channel xgerhard, reply in Dutch <i>(This instruction could also be written in Dutch)</i></li>
                                </ul>
                            </p>
                            <div class="form-group">
                                <label for="start_instructions">AI instructions</label>
                                <textarea class="form-control" id="start_instructions" name="start_instructions" style="height:100px" maxlength="500">@if( auth()->user()->settings ){{ auth()->user()->settings->start_instructions}}@endif</textarea>
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="conversation-id">
                            <h2 class="section-heading">Conversation ID</h2>
                            <p>Viewers can always continue their own last conversation by starting with a +, for example: <b>!ai + tell me more</b>.<br/>
                                When enabled, each message also ends with a hashtag and a code, for example #abc. Anyone can use this code to continue that conversation. View examples: <a href="/#conversations" target="blank">here</a>.<br/>
                                It's disabled by default, which leaves space for a few extra characters of other info.
                            </p>
                            <div class="form-group">
                                <input type="checkbox" @if( auth()->user()->settings and auth()->user()->settings->show_conversation_id) checked="checked" @endif name="show_conversation_id" id="show_conversation_id"/> Show conversation ID/hashtag at the end of the message
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="mention-user">
                            <h2 class="section-heading">Mention user</h2>
                            <p>At the start of each message we @mention the user who called the command.<br/>
                            By disabling this setting, the username of the viewer will be removed from the message, freeing up space for a few extra characters of other info.
                            </p>
                            <div class="form-group">
                                <input type="checkbox" @if( auth()->user()->settings and auth()->user()->settings->mention_user) checked="checked" @endif name="mention_user" id="mention_user"/> Mention user at the start of the message
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="sponsor-heart">
                            <h2 class="section-heading">Sponsor heart</h2>
                            <p>Are you a sponsor? Awesome! As appreciation we show a ❤️ emoji in front of your username when you use our app.<br/>
                            Want to become a sponsor, head over to: <a href="https://github.com/sponsors/xgerhard" target="blank">github.com/sponsors/xgerhard</a>.<br/> 
                            By disabling this setting, the emoji won't be shown in front of your username.
                            </p>
                            <div class="form-group">
                                <input type="checkbox" @if( auth()->user()->settings and auth()->user()->settings->show_sponsor_heart) checked="checked" @endif name="show_sponsor_heart" id="show_sponsor_heart"/> Show sponsor ❤️ emoji
                            </div>
                        </section><!--//section-->
                        <section class="docs-section" id="save">
                            <h2 class="section-heading">Save settings</h2>
                            <p><button type="submit" id="save_settings" name="save_settings" class="btn btn-primary">Save settings</button><p>
                        </section>
                    </form>
                </article><!--//docs-article-->

                <article class="docs-article" id="help">
                    <header class="docs-header">
                        <h1 class="docs-heading">Help</h1>
                        <section class="docs-intro">
                            <p></p>
                        </section><!--//docs-intro-->
                    </header>
                     <section class="docs-section" id="contact">
                        <h2 class="section-heading">Contact</h2>
                        <p>Need help with the installation, any questions, or a thank you, feel free to send me a mail at: <a href="mailto:hi@gerhard.dev" target="blank">hi@gerhard.dev</a>.</p>
                    </section><!--//section-->
                    <section class="docs-section" id="contribute">
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

