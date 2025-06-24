// ReactifyWP Compatible React App
// This file is designed to work with the WP Reactify plugin structure

(function() {
    'use strict';
    
    // Wait for React to be available (from CDN or WordPress)
    function waitForReact(callback) {
        if (window.React && window.ReactDOM) {
            callback();
        } else {
            // Load React from CDN if not available
            const reactScript = document.createElement('script');
            reactScript.src = 'https://unpkg.com/react@18/umd/react.production.min.js';
            reactScript.onload = function() {
                const reactDOMScript = document.createElement('script');
                reactDOMScript.src = 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js';
                reactDOMScript.onload = callback;
                document.head.appendChild(reactDOMScript);
            };
            document.head.appendChild(reactScript);
        }
    }
    
    // Quiz App Component
    function QuizApp() {
        const [currentQuestion, setCurrentQuestion] = React.useState(0);
        const [answers, setAnswers] = React.useState({});
        const [showResults, setShowResults] = React.useState(false);

        const questions = [
            {
                id: 1,
                question: "What type of sleeper are you?",
                options: [
                    "Side sleeper",
                    "Back sleeper", 
                    "Stomach sleeper",
                    "Combination sleeper"
                ]
            },
            {
                id: 2,
                question: "How firm do you like your mattress?",
                options: [
                    "Very soft",
                    "Medium soft",
                    "Medium firm",
                    "Very firm"
                ]
            },
            {
                id: 3,
                question: "Do you sleep hot or cold?",
                options: [
                    "I sleep very hot",
                    "I sleep somewhat hot",
                    "I sleep neutral",
                    "I sleep cold"
                ]
            }
        ];

        const handleAnswer = (questionId, answer) => {
            setAnswers(prev => ({
                ...prev,
                [questionId]: answer
            }));
        };

        const nextQuestion = () => {
            if (currentQuestion < questions.length - 1) {
                setCurrentQuestion(prev => prev + 1);
            } else {
                setShowResults(true);
            }
        };

        const resetQuiz = () => {
            setCurrentQuestion(0);
            setAnswers({});
            setShowResults(false);
        };

        const progress = ((currentQuestion + 1) / questions.length) * 100;
        const currentQ = questions[currentQuestion];
        const hasAnswer = answers[currentQ?.id];

        if (showResults) {
            return React.createElement('div', { className: 'quiz-results' },
                React.createElement('h2', null, '🎉 Quiz Complete!'),
                React.createElement('div', { className: 'score' }, `${Object.keys(answers).length}/${questions.length}`),
                React.createElement('p', null, 'Thank you for completing the sleep quiz!'),
                React.createElement('p', null, 'Based on your answers, we\'ll recommend the perfect mattress for you.'),
                React.createElement('button', { 
                    className: 'quiz-button',
                    onClick: resetQuiz 
                }, 'Take Quiz Again')
            );
        }

        return React.createElement('div', { className: 'quiz-container' },
            React.createElement('div', { className: 'quiz-header' },
                React.createElement('h1', null, '🛏️ Sleep Quiz'),
                React.createElement('p', null, 'Find your perfect mattress match')
            ),
            React.createElement('div', { className: 'progress-bar' },
                React.createElement('div', { 
                    className: 'progress-fill',
                    style: { width: `${progress}%` }
                })
            ),
            React.createElement('div', { className: 'quiz-section' },
                React.createElement('div', { className: 'question' },
                    React.createElement('h3', null, `Question ${currentQuestion + 1} of ${questions.length}`),
                    React.createElement('h3', null, currentQ.question),
                    React.createElement('div', { className: 'options' },
                        currentQ.options.map((option, index) =>
                            React.createElement('div', {
                                key: index,
                                className: `option ${answers[currentQ.id] === option ? 'selected' : ''}`,
                                onClick: () => handleAnswer(currentQ.id, option)
                            }, option)
                        )
                    )
                ),
                React.createElement('button', {
                    className: 'quiz-button',
                    onClick: nextQuestion,
                    disabled: !hasAnswer
                }, currentQuestion === questions.length - 1 ? 'See Results' : 'Next Question')
            )
        );
    }
    
    // Auto-mount function that works with ReactifyWP
    function mountQuizApp() {
        // Look for ReactifyWP containers
        const containers = [
            ...document.querySelectorAll('[data-reactify-slug*="quiz"]'),
            ...document.querySelectorAll('[id*="reactify"]'),
            ...document.querySelectorAll('.reactify-container')
        ];
        
        containers.forEach(container => {
            if (container && !container.hasAttribute('data-quiz-mounted')) {
                container.setAttribute('data-quiz-mounted', 'true');
                
                // Clear any existing content
                container.innerHTML = '';
                
                // Mount React app
                const root = ReactDOM.createRoot ? 
                    ReactDOM.createRoot(container) : 
                    null;
                    
                if (root) {
                    root.render(React.createElement(QuizApp));
                } else {
                    ReactDOM.render(React.createElement(QuizApp), container);
                }
                
                console.log('Quiz app mounted to:', container);
            }
        });
    }
    
    // Initialize when React is ready
    waitForReact(function() {
        // Mount immediately if DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', mountQuizApp);
        } else {
            mountQuizApp();
        }
        
        // Also try mounting after a short delay (for dynamic content)
        setTimeout(mountQuizApp, 100);
        setTimeout(mountQuizApp, 500);
        setTimeout(mountQuizApp, 1000);
    });
    
    // Expose globally for manual mounting
    window.ReactifyQuizApp = {
        mount: mountQuizApp,
        component: QuizApp
    };
    
})();
