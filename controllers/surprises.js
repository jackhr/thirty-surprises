const Surprise = require('../models/surprise');
const { DateTime } = require('luxon');

module.exports = {
    viewed,
    create,
    update,
    delete: deleteOne
};


async function viewed(req, res) {
    try {
        const surprise = await Surprise.findById(req.params.id);

        surprise.viewed = true;

        await surprise.save();
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }

}

async function deleteOne(req, res) {
    try {
        const surprise = await Surprise.findByIdAndDelete(req.params.id);
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }
}

async function update(req, res) {
    try {
        const surprise = await Surprise.findById(req.params.id);

        surprise.title = req.body.title;
        surprise.description = req.body.description;
        surprise.magnitude = req.body.magnitude;
        surprise.variety = req.body.variety;
        surprise.iconClass = req.body.iconClass;
        surprise.revealDate = req.body.revealDate;
        surprise.viewed = req.body.viewed === 'true';

        if (req.body.completed === 'true') {
            if (!surprise.completedAt) surprise.completedAt = new Date();
        } else {
            surprise.completedAt = undefined;
        }

        await surprise.save();
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }
}

async function create(req, res) {
    try {
        const surprise = new Surprise(req.body);
        surprise.revealDate = DateTime.fromISO(req.body.revealDate, { zone: 'America/New_York' }).toJSON();
        await surprise.save();
        res.json(surprise);
    } catch(error) {
        res.json({
            error: error.message
        });
    }
}