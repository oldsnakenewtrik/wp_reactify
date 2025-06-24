import React, { useState, useEffect } from 'react';
import QuizForm from './components/QuizForm';
import Results from './components/Results';
import SplashPage from './components/steps/SplashPage';

function App() {
  const [quizResults, setQuizResults] = useState(null);
  const [showQuiz, setShowQuiz] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    console.log('SonderCare App component mounted');
  }, []);

  const handleStartQuiz = () => {
    console.log('Starting SonderCare quiz');
    setShowQuiz(true);
  };

  const handleQuizComplete = (results) => {
    console.log('SonderCare quiz completed', results);
    setQuizResults(results);
  };

  const handleRestart = () => {
    console.log('Restarting SonderCare quiz');
    setQuizResults(null);
    setShowQuiz(false);
  };

  if (error) {
    return <div>Error: {error.message}</div>;
  }

  return (
    <div className="min-h-screen flex flex-col border-8 border-[#1e3050]">
      <header className="bg-[#1E3050] text-[#D4B26A] text-center p-5 relative">
        <p className="text-white text-base italic m-0">Welcome to the</p>
        <h1 className="text-4xl font-bold m-0">SonderCare Bed Selector</h1>
        <div className="absolute right-24 top-1/2 transform -translate-y-1/2">
          <img 
            src="https://onmdevstg.wpenginepowered.com/wp-content/plugins/sonderbedselectorwpv2/images/logo_placeholder.png" 
            alt="SonderCare logo" 
            className="h-14 -mt-2"
          />
        </div>
      </header>
      <div className="flex-grow bg-white flex items-center justify-center p-4">
        <div className="w-full max-w-3xl">
          {quizResults ? (
            <Results results={quizResults} onRestart={handleRestart} />
          ) : showQuiz ? (
            <QuizForm setQuizResults={handleQuizComplete} />
          ) : (
            <SplashPage handleNext={handleStartQuiz} />
          )}
        </div>
      </div>
    </div>
  );
}

export default App;
