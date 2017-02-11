<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Main extends CI_Controller {

    function __construct()
    {
        parent::__construct();
        $this->load->model('main_model', '', TRUE);

        // Get list of active sites
        $this->data['active_sites'] = $this->main_model->get_active_sites();
        $this->data['user'] = $this->get_user_by_session();

        $this->confidence_minimum = 3;
    }

    public function landing()
    {
        $data = $this->data;
        $data['page_title'] = site_name();
        $this->load->view('templates/header', $data);
        $this->load->view('templates/toolbar', $data);
        $this->load->view('landing', $data);
        $this->load->view('templates/scripts', $data);
        $this->load->view('templates/footer', $data);
    }

    public function homepage($slug, $offset = 0)
    {
        $data = $this->data;
        $data['current_site'] = $this->main_model->get_current_site($slug);
        $data['validation_errors'] = $this->session->flashdata('validation_errors');

        // Get recent posts
        $input['site_key'] = $data['current_site']['id'];
        $input['offset'] = $offset;
        $input['limit'] = 10;
        $data['posts'] = $this->main_model->get_posts($input);

        $data['page_title'] = $slug;
        $data['slug'] = $slug;
        $data['offset'] = $offset;
        $data['limit'] = $input['limit'];
        $this->load->view('templates/header', $data);
        $this->load->view('templates/toolbar', $data);
        $this->load->view('sites/' . $slug . '/style', $data);
        $this->load->view('sites/' . $slug . '/homepage', $data);
        $this->load->view('templates/scripts', $data);
        $this->load->view('sites/' . $slug . '/script', $data);
        $this->load->view('templates/footer', $data);
    }

    public function queue($slug)
    {
        $data = $this->data;
        $data['current_site'] = $this->main_model->get_current_site($slug);
        $input['site_key'] = $data['current_site']['id'];

        // If post request, handle post request
        $data['review_result'] = false;
        if (is_whole_int($this->input->post('post_id')) && is_whole_int($this->input->post('offence')) && is_whole_int($this->input->post('action'))) {
            $user = $this->get_user_by_session();
            $input['user_key'] = 0;
            $user_input['offence_key'] = $this->input->post('offence');
            $input['offence_key'] = $user_input['offence_key'];
            $input['post_key'] = $this->input->post('post_id');
            $input['action_key'] = $this->input->post('action');

            // Insert as new review
            $this->main_model->create_review($input);

            // Update post offence if no confidence
            $reviewed_post = $this->main_model->get_post_by_id($input);
            if ($reviewed_post['offence_key'] != $user_input['offence_key'] && $reviewed_post['confidence'] === 1) {
                $input['offence_key'] = $user_input['offence_key'];
            }
            // Else increase post confidence on agree
            else if ($reviewed_post['offence_key'] === $input['offence_key']) {
                $input['confidence'] = $reviewed_post['confidence'] + 1;
                $input['offence_key'] = $reviewed_post['offence_key'];
            }
            // Else decrease post confidence on disagree
            else {
                $input['confidence'] = $reviewed_post['confidence'] - 1;
                $input['offence_key'] = $reviewed_post['offence_key'];
            }
            $input['review_tally'] = $reviewed_post['review_tally'] + 1;
            $this->main_model->update_post($input);

            // Tell client result
            if ($reviewed_post['offence_key'] === $user_input['offence_key']) {
                echo 'marco';
                $data['review_result'] = 'success';
                $sess_array = array(
                    'id' => $user['id'],
                    'pass' => $user['pass'] + 1,
                    'fail' => $user['fail'],
                    'streak' => $user['streak'] + 1,
                );
                $this->session->set_userdata('user', $sess_array);
            }
            else {
                echo 'polo';
                $data['review_result'] = 'fail';
                $sess_array = array(
                    'id' => $user['id'],
                    'pass' => $user['pass'],
                    'fail' => $user['fail'] + 1,
                    'streak' => 0,
                );
                $this->session->set_userdata('user', $sess_array);
            }

        }

        // Get a random post
        $data['user'] = $this->get_user_by_session();
        $data['post'] = $this->main_model->get_random_post($input);
        $data['offences'] = $this->main_model->get_offences_by_site($data['current_site']['id']);
        $data['actions'] = $this->main_model->get_actions();

        $data['page_title'] = $slug . ' Moderator Queue';
        $data['slug'] = $slug;
        $this->load->view('templates/header', $data);
        $this->load->view('templates/toolbar', $data);
        $this->load->view('sites/' . $slug . '/style', $data);
        $this->load->view('sites/' . $slug . '/queue', $data);
        $this->load->view('templates/queue_form', $data);
        $this->load->view('templates/scripts', $data);
        $this->load->view('sites/' . $slug . '/script', $data);
        $this->load->view('templates/footer', $data);
    }

    public function new_post($slug)
    {
        $data = $this->data;
        $data['current_site'] = $this->main_model->get_current_site($slug);

        // Validation
        $this->form_validation->set_rules('username', 'username', 'trim|required|max_length[100]');
        $this->form_validation->set_rules('content', 'content', 'trim|required|max_length[10000]');
        
        // Fail
        if ($this->form_validation->run() == FALSE) {
            $this->session->set_flashdata('validation_errors', validation_errors());
        }
        // Pass
        else {
            // Input
            $input['site_key'] = $data['current_site']['id'];
            $input['username'] = $this->input->post('username');
            $input['content'] = $this->input->post('content');
            $input['image'] = '';

            // Create post
            $this->main_model->create_post($input);
        }

        header('Location: ' . base_url() . 'site/' . $slug);
    }

    public function login()
    {
        $data = $this->data;

        // Handle post request

        echo '<pre>'; print_r($_POST); echo '</pre>';
    }

    public function new_user()
    {
        $data = $this->data;

        // Handle post request

        echo '<pre>'; print_r($_POST); echo '</pre>';
    }

    public function get_user_by_session()
    {
        $user_session = $this->session->userdata('user');
        if (!$user_session) {
            $sess_array = array(
                'id' => uniqid(),
                'pass' => 0,
                'fail' => 0,
                'streak' => 0,
            );
            $this->session->set_userdata('user', $sess_array);
            $user_session = $this->session->userdata('user');
        }
        return $user_session;
    }

    public function about()
    {
        $data = $this->data;
        $data['page_title'] = 'more info';
        $this->load->view('templates/header', $data);
        $this->load->view('templates/toolbar', $data);
        $this->load->view('pages/about', $data);
        $this->load->view('templates/scripts', $data);
        $this->load->view('templates/footer', $data);
    }

}
