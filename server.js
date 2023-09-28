const createError = require('http-errors');
const express = require('express');
const path = require('path');
const cookieParser = require('cookie-parser');
const logger = require('morgan');
const methodOverride = require('method-override');
const session = require('express-session');
const checkSession = require('./config/checkSession');
const checkToken = require('./config/checkToken');
const favicon = require('serve-favicon');

require('dotenv').config();
require('./config/database');
// require('./config/passport');

var app = express();

// view engine setup
app.set('views', path.join(__dirname, 'views'));
app.set('view engine', 'ejs');

// app.use(favicon(path.join(__dirname, 'build', 'favicon.ico')));
app.use(logger('dev'));
app.use(express.json());
app.use(express.urlencoded({ extended: false }));
app.use(cookieParser());
app.use(express.static(path.join(__dirname, 'public')));
app.use(methodOverride('_method'));
app.use(session({
    secret: process.env.EXPRESS_SESSION_SECRET,
    resave: false,
    saveUninitialized: false,
    cookie: { 
        maxAge: parseInt(process.env.SESSION_MAX_AGE),
    }
}));

// Middleware to verify session and token
// Be sure to mount before routes
app.use(checkSession);
app.use(checkToken);

const indexRouter = require('./routes/index');
const surprisesRouter = require('./routes/surprises');
const adminRouter = require('./routes/admin');

app.use('/', indexRouter);
app.use('/surprises', surprisesRouter);
app.use('/admin', adminRouter);

// catch 404 and forward to error handler
app.use(function(req, res, next) {
  next(createError(404));
});

// error handler
app.use(function(err, req, res, next) {
  // set locals, only providing error in development
  res.locals.message = err.message;
  res.locals.error = req.app.get('env') === 'development' ? err : {};

  // render the error page
  res.status(err.status || 500);
  res.render('error');
});

module.exports = app;
