const jwt = require('jsonwebtoken');

module.exports = function(req, res, next) {
    const resetSession = () => {
        req.session.regenerate();
        req.session.user = null;
        req.session.token = null;
        req.session.loggedIn = null;
    }
    if (req.session.loggedIn) {
        let token = req.session.token;
        if (token) {
        token = token.replace('Bearer ', '');
        jwt.verify(token, process.env.SECRET, function(err, decoded) {
            if (err) resetSession();
            return next();
        });
        }
    } else {
        return next();
    }
};