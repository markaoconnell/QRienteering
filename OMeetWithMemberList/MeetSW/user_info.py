class stick_info:

    def __init__(self, stick_number, qr_result_string):
        self.stick_number = stick_number
        self.qr_result_string = qr_result_string
        self.lookup_info = None
        self.widget = None
        self.missed_finish = False
        self.download_possible = True

    def add_lookup_info(self, lookup_info):
        self.lookup_info = lookup_info

    def get_lookup_info(self):
        return(self.lookup_info)

    def set_missed_finish(self, missed_finish):
        self.missed_finish = missed_finish

    def get_missed_finish(self):
        return(self.missed_finish)

    def set_download_possible(self, results_found_on_stick):
        self.download_possible = results_found_on_stick

    def get_download_possible(self):
        return(self.download_possible)

    def add_widget(self, widget):
        self.widget = widget

    def get_widget(self):
        return(self.widget)


class found_user_info:
    def __init__(self, name=None, member_id=None, email=None, club=None, stick=None, cell_phone=None, course=None, nre_info=None, registration_info=None):
        self.name = name
        self.member_id = member_id
        self.email = email
        self.stick = stick
        self.cell_phone = cell_phone
        self.course = course
        self.nre_info = nre_info
        self.club = club
        self.registration_info = registration_info
