const serverless = require('serverless-http');
const app = require('../../server');

const serverlessHandler = serverless(app);

module.exports.handler = async (event, context) => {
    // Normalize path to ensure Express matches /api routes
    if (event.path) {
        if (event.path.startsWith('/.netlify/functions/api')) {
            event.path = event.path.replace('/.netlify/functions/api', '/api');
        }
        if (!event.path.startsWith('/api')) {
            event.path = '/api' + (event.path.startsWith('/') ? event.path : '/' + event.path);
        }
    }
    return await serverlessHandler(event, context);
};
