var quick_tips = new EnjoyHint({});
var mainQuickTips = false;

var menu_script_steps = [
    {
        'next .navbar-brand' : 'Welcome to Incode! This guide will tell you how to use the site',
    },
    {
        'next .navbar-text' : 'Timer. Shows the time until the end of the training.',
    },
    {
        'next .nav-link' : 'Link page with the list of trainings.',
    },
    {
        'next .btn' : 'Logout.',
    },
];

var problems_script_steps = [
    {
        'next .scoreboard-team' : 'Information about the problems of this training.',
    },
    {
        'click .problem_link' : 'Clicking on the problem name the screen will open to solve it',
    }
];

var edit_source_script_steps = [
    {
        'next .col' : 'Problem text',
    },
    {
        'next .tab-content source-tab' : 'Code editor to solve the problem.',
    },
    {
        'next #form_language' : 'Programming language selection.',
    },
    {
        'next #example' : 'Code example in the selected programming language.',
    },
    {
        'click #ajax_submit_btn' : 'Check solution button.',
    }
];
var trainings_script_steps = [
    {
        'next #trainings' : 'Information about available trainings.',
    },
    {
        'click .training_link' : 'Clicking on the name of the training the screen will open  with the tasks of the selected training.',
    }
];

function quickTipsStart () {
    quick_tips.set(menu_script_steps);

    if (document.getElementById("scoreboard-team") !== null) {
        quick_tips.set(menu_script_steps.concat(problems_script_steps));
    }
    if (document.getElementById("trainings") !== null) {
        quick_tips.set(menu_script_steps.concat(trainings_script_steps));
    }
    if (document.getElementById("problem-text") !== null) {
        quick_tips.set(menu_script_steps.concat(edit_source_script_steps));
    }

    quick_tips.run();
    mainQuickTips = true;
}


function problemsQuickTipsStart() {

    quick_tips.set(problems_script_steps);

    quick_tips.run();
}

function editQuickTipsStart() {
    quick_tips.set(edit_source_script_steps);

    quick_tips.run();
}
